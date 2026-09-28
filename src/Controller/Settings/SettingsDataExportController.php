<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Export\WorkoutExportFormatEnum;
use App\Repository\WorkoutRepository;
use App\Service\Export\WorkoutExportCsvWriter;
use App\Service\Export\WorkoutExportJsonWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réglages › Mes données : téléchargement de tout l'historique de séances de l'utilisateur, en CSV
 * (tableur) ou en JSON (données brutes). Écrit au fil de l'eau dans la réponse, sans fichier
 * temporaire. Un format inconnu dans l'URL donne une 404 (résolution de l'enum par Symfony).
 */
#[IsGranted('ROLE_USER')]
final class SettingsDataExportController extends AbstractController
{
    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
        private readonly WorkoutExportCsvWriter $csvWriter,
        private readonly WorkoutExportJsonWriter $jsonWriter,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/export/{format}',
            'fr' => '/reglages/export/{format}',
            'it' => '/impostazioni/esporta/{format}',
            'es' => '/ajustes/exportar/{format}',
            'pt' => '/definicoes/exportar/{format}',
            'de' => '/einstellungen/export/{format}',
            'nl' => '/instellingen/exporteren/{format}',
            'pl' => '/ustawienia/eksport/{format}',
        ],
        name: 'app_settings_data_export',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(WorkoutExportFormatEnum $format, Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $workouts = $this->workoutRepository->findForExportByUser($user);
        // Capturée ici : le contenu est écrit après le traitement de la requête, sa langue n'est
        // alors plus active pour le traducteur.
        $locale = $request->getLocale();

        $response = new StreamedResponse(function () use ($format, $user, $workouts, $locale): void {
            $stream = fopen('php://output', 'wb') ?: throw new \LogicException('Unable to open the output stream.');
            match ($format) {
                WorkoutExportFormatEnum::CSV => $this->csvWriter->write($user, $workouts, $stream, $locale),
                WorkoutExportFormatEnum::JSON => $this->jsonWriter->write($workouts, $stream, $locale),
            };
            fclose($stream);
        });
        $response->headers->set('Content-Type', $format->contentType());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            \sprintf('enurus-workouts-%s.%s', new \DateTimeImmutable()->format('Y-m-d'), $format->value),
        ));

        return $response;
    }
}
