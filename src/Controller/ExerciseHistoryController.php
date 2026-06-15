<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ExerciseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class ExerciseHistoryController extends AbstractController
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: [
        'fr' => '/bibliotheque',
        'en' => '/library',
        'it' => '/biblioteca',
        'es' => '/biblioteca',
        'pt' => '/biblioteca',
        'de' => '/bibliothek',
        'nl' => '/bibliotheek',
        'pl' => '/biblioteka',
    ], name: 'app_exercise_history', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $exercises = $this->exerciseRepository->findAvailableForUser($user);
        $sorted = $this->sortAlphabetically($exercises, $user);

        return $this->render('exercise/history/index.html.twig', [
            'exercises' => $sorted,
            'totalCount' => count($sorted),
        ]);
    }

    /**
     * Trie les exercices par ordre alphabétique en tenant compte de la locale.
     * Les exercices publics ont un name = clé de traduction → résolution avant tri.
     * Les exercices customs ont un name = chaîne littérale → tri direct.
     *
     * @param list<Exercise> $exercises
     * @return list<Exercise>
     */
    private function sortAlphabetically(array $exercises, User $user): array
    {
        $locale = $user->locale ?? LocaleAllowedEnum::EN->value;

        usort($exercises, function (Exercise $a, Exercise $b) use ($locale): int {
            return strcoll(
                mb_strtolower($this->resolveName($a, $locale)),
                mb_strtolower($this->resolveName($b, $locale)),
            );
        });

        return $exercises;
    }

    private function resolveName(Exercise $exercise, string $locale): string
    {
        if ($exercise->isPublic) {
            return $this->translator->trans(
                $exercise->name,
                domain: 'exercise',
                locale: $locale,
            );
        }

        return $exercise->name;
    }
}
