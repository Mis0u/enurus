<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Enum\Export\WorkoutExportFormatEnum;
use App\Service\Export\WorkoutExportFileGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réglages › Mes données : téléchargement de tout l'historique de séances de l'utilisateur, en CSV
 * (tableur) ou en JSON (données brutes). Le fichier est préparé en entier avant l'envoi (taille
 * annoncée, aucun fichier coupé en cas d'erreur) puis supprimé. Un format inconnu dans l'URL donne
 * une 404 (résolution de l'enum par Symfony).
 */
#[IsGranted('ROLE_USER')]
final class SettingsDataExportController extends AbstractController
{
    public function __construct(
        private readonly WorkoutExportFileGenerator $fileGenerator,
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
    public function __invoke(WorkoutExportFormatEnum $format, Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $response = $this->file(
            $this->fileGenerator->generate($user, $format, $request->getLocale()),
            \sprintf('enurus-workouts-%s.%s', new \DateTimeImmutable()->format('Y-m-d'), $format->value),
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );
        $response->headers->set('Content-Type', $format->contentType());

        return $response->deleteFileAfterSend();
    }
}
