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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ContactThreadRepository $contactThreadRepository,
        private readonly AdminDashboardStatsService $adminDashboardStatsService,
        private readonly TranslatorInterface $translator,
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
        return Dashboard::new()
            ->setTitle($this->trans('backend_title', [
                'brand' => $this->translator->trans('name', [], 'brand', 'fr'),
            ]))
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

        yield MenuItem::linkToDashboard($this->trans('admin.menu.home'), 'fa fa-home');
        yield MenuItem::linkTo(UserCrudController::class, $this->trans('admin.menu.user'), 'fa fa-users');
        yield MenuItem::linkTo(ExerciseCrudController::class, $this->trans('admin.menu.exercise'), 'fa fa-dumbbell');
        yield MenuItem::linkTo(RoutineCrudController::class, $this->trans('admin.menu.routine'), 'fa fa-list-check');
        yield MenuItem::linkTo(WorkoutCrudController::class, $this->trans('admin.menu.workout'), 'fa fa-calendar-days');
        yield MenuItem::linkTo(ContactThreadCrudController::class, $this->trans('admin.menu.thread'), 'fa fa-envelope')
            ->setBadge(0 < $awaitingReplyCount ? $awaitingReplyCount : false, 'danger')
        ;
        yield MenuItem::linkTo(ContactBroadcastCrudController::class, $this->trans('admin.menu.broadcast'), 'fa fa-bullhorn');
        yield MenuItem::linkTo(DeletedAccountTraceCrudController::class, $this->trans('admin.menu.deleted_account_trace'), 'fa fa-user-slash');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'admin', 'fr');
    }
}
