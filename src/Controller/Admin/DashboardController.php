<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ContactThreadRepository;
use App\Service\Dashboard\Admin\AdminDashboardStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ContactThreadRepository $contactThreadRepository,
        private readonly AdminDashboardStatsService $adminDashboardStatsService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $assetPackages,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $this->adminDashboardStatsService->getData(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        $logoUrl = $this->assetPackages->getUrl('images/enurus_logo_horizontal.png');

        return Dashboard::new()
            ->setTitle(sprintf(
                '<img src="%s" alt="%s" style="height: 26px; width: auto; vertical-align: middle; margin-right: 8px;"> %s',
                htmlspecialchars($logoUrl, ENT_QUOTES),
                htmlspecialchars($this->translator->trans('name', [], 'brand', 'fr'), ENT_QUOTES),
                htmlspecialchars($this->trans('admin.backend_title'), ENT_QUOTES),
            ))
            ->setFaviconPath('images/favicon/favicon.ico')
            ->setLocales(['fr'])
        ;
    }

    /**
     * Entrée AssetMapper dédiée (`assets/admin.js`, Stimulus seul) — jamais l'entrée `app` du site
     * public, qui embarquerait le CSS Tailwind dans le back-office EasyAdmin.
     */
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('admin')
        ;
    }

    /**
     * @return iterable<MenuItemInterface>
     */
    public function configureMenuItems(): iterable
    {
        $awaitingReplyCount = $this->contactThreadRepository->countAwaitingAdminReply();

        yield MenuItem::linkToUrl($this->trans('admin.menu.back_to_app'), 'fa fa-arrow-left', $this->urlGenerator->generate('app_dashboard', [
            '_locale' => 'fr',
        ]));
        yield MenuItem::linkToDashboard($this->trans('admin.menu.home'), 'fa fa-home');

        yield MenuItem::section($this->trans('admin.menu.section.user'), 'fa fa-users');
        yield MenuItem::linkTo(UserCrudController::class, $this->trans('admin.menu.user'), 'fa fa-users');
        yield MenuItem::linkTo(DeletedAccountTraceCrudController::class, $this->trans('admin.menu.deleted_account_trace'), 'fa fa-user-slash');

        yield MenuItem::section($this->trans('admin.menu.section.content'), 'fa fa-dumbbell');
        yield MenuItem::linkTo(ExerciseCrudController::class, $this->trans('admin.menu.exercise'), 'fa fa-dumbbell');
        yield MenuItem::linkTo(RoutineCrudController::class, $this->trans('admin.menu.routine'), 'fa fa-list-check');
        yield MenuItem::linkTo(WorkoutCrudController::class, $this->trans('admin.menu.workout'), 'fa fa-calendar-days');

        yield MenuItem::section($this->trans('admin.menu.section.thread'), 'fa fa-envelope');
        yield MenuItem::linkTo(ContactThreadCrudController::class, $this->trans('admin.menu.thread'), 'fa fa-envelope')
            ->setBadge(0 < $awaitingReplyCount ? $awaitingReplyCount : false, 'danger')
        ;
        yield MenuItem::linkTo(ContactBroadcastCrudController::class, $this->trans('admin.menu.broadcast'), 'fa fa-bullhorn');

        yield MenuItem::section($this->trans('admin.menu.section.settings'), 'fa fa-flag-checkered');
        yield MenuItem::linkTo(RegistrationMilestoneSettingCrudController::class, $this->trans('admin.menu.registration_milestone'), 'fa fa-flag-checkered');
        yield MenuItem::linkTo(ContactNotificationSettingCrudController::class, $this->trans('admin.menu.contact_notification_setting'), 'fa fa-bell-slash');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'admin', 'fr');
    }
}
