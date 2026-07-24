<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ContactThreadRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ContactThreadRepository $contactThreadRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): RedirectResponse
    {
        $url = $this->adminUrlGenerator->setController(ContactThreadCrudController::class)->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('FitTracker Admin')
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

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }
}
