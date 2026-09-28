<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\Exercise\ExerciseNameTranslator;
use App\Service\Utils\WeightConverterService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Export CSV des séances, pour un tableur : une ligne par série, en-têtes traduits, poids dans
 * l'unité de l'utilisateur. Séparateurs selon la langue (Excel met sinon tout dans une colonne) :
 * « , » et point décimal en anglais, « ; » et virgule décimale pour les autres langues du site.
 * Les notes (texte libre, multi-lignes) et les photos ne sont que dans l'export JSON / l'appli.
 */
final readonly class WorkoutExportCsvWriter
{
    // Sans BOM, Excel lit un CSV UTF-8 comme du Latin-1 : les accents s'affichent mal.
    private const string UTF8_BOM = "\u{FEFF}";

    private const array COLUMNS = ['date', 'time', 'routine', 'workout_duration', 'mood', 'exercise', 'set', 'weight', 'unit', 'reps', 'set_duration', 'distance'];

    private const int WEIGHT_DECIMALS = 2;

    public function __construct(
        private TranslatorInterface $translator,
        private WeightConverterService $weightConverter,
        private ExerciseNameTranslator $exerciseNameTranslator,
    ) {
    }

    /**
     * `$locale` explicite : écrit dans une réponse streamée, après le traitement de la requête dont
     * la langue n'est alors plus active.
     *
     * @param list<Workout> $workouts
     * @param resource      $stream
     */
    public function write(User $user, array $workouts, $stream, string $locale): void
    {
        $format = new CsvNumberFormat($locale);
        fwrite($stream, self::UTF8_BOM);
        $this->writeRow($stream, $this->headerRow($locale), $format);

        foreach ($workouts as $workout) {
            foreach ($this->workoutRows($user, $workout, $format, $locale) as $row) {
                $this->writeRow($stream, $row, $format);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function headerRow(string $locale): array
    {
        return array_map(
            fn (string $column): string => $this->translator->trans('settings.data_export.csv_column.' . $column, [], 'navigation', $locale),
            self::COLUMNS,
        );
    }

    /**
     * @return iterable<list<string>>
     */
    private function workoutRows(User $user, Workout $workout, CsvNumberFormat $format, string $locale): iterable
    {
        $workoutCells = $this->workoutCells($workout, $locale);

        foreach ($workout->workoutExercises as $workoutExercise) {
            foreach (array_values($workoutExercise->exerciseSets->toArray()) as $index => $set) {
                yield [...$workoutCells, $this->exerciseNameTranslator->translate($workoutExercise->exercise, $locale), (string) ($index + 1), ...$this->setCells($user, $workoutExercise, $set, $format)];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function workoutCells(Workout $workout, string $locale): array
    {
        return [
            $workout->performedAt->format('Y-m-d'),
            $workout->performedAt->format('H:i'),
            null !== $workout->routine ? $workout->routine->name : $this->translator->trans('workout.list.free_session', [], 'navigation', $locale),
            null !== $workout->duration ? (string) $workout->duration : '',
            null !== $workout->mood ? $this->translator->trans('workout.mood.' . $workout->mood->value, [], 'navigation', $locale) : '',
        ];
    }

    /**
     * Seules les colonnes qui ont un sens pour le type de mesure sont remplies : répétitions pour
     * poids × reps, durée tenue pour un exercice chronométré, distance pour un exercice en distance.
     * Le poids (lest pour un exercice au poids du corps) est optionnel hors poids × reps.
     *
     * @return list<string>
     */
    private function setCells(User $user, WorkoutExercise $workoutExercise, ExerciseSet $set, CsvNumberFormat $format): array
    {
        $type = $workoutExercise->exercise->measurementType;
        $hasWeight = MeasurementType::WEIGHT_REPS === $type || 0.0 < $set->weight;
        $weight = $this->weightConverter->convertToLbs($set->weight, $user->unitOfMeasure);

        return [
            $hasWeight ? $format->decimal($weight, self::WEIGHT_DECIMALS) : '',
            $hasWeight ? $user->unitOfMeasure->value : '',
            MeasurementType::WEIGHT_REPS === $type ? (string) $set->reps : '',
            MeasurementType::TIME === $type ? (string) $set->duration : '',
            MeasurementType::DISTANCE === $type ? (string) $set->distance : '',
        ];
    }

    /**
     * @param resource     $stream
     * @param list<string> $row
     */
    private function writeRow($stream, array $row, CsvNumberFormat $format): void
    {
        fputcsv($stream, $row, $format->columnSeparator, '"', '');
    }
}
