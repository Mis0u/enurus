<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use App\Entity\Workout;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

/**
 * Carte d'une séance (Mes séances, séances d'une connexion). Sur mobile, c'est une grille à deux
 * colonnes (infos | boutons d'action) : la date et les infos doivent être placées explicitement en
 * colonne 1. Laissées au placement automatique, en lecture seule (pas de boutons), elles se
 * répartissaient sur les deux colonnes et se chevauchaient.
 */
final class SessionRowTest extends KernelTestCase
{
    /**
     * @return array<string, array{bool}>
     */
    public static function readOnlyProvider(): array
    {
        return [
            'own workouts' => [false],
            'workouts of a connection' => [true],
        ];
    }

    #[DataProvider('readOnlyProvider')]
    public function testDateAndInfosStayInTheFirstColumnOnMobile(bool $readOnly): void
    {
        $row = $this->renderRow($readOnly)->filter('.workout-row');

        self::assertCount(2, $row->children()->reduce(
            static fn (Crawler $child): bool => str_contains((string) $child->attr('class'), 'max-md:col-start-1'),
        ));
    }

    private function renderRow(bool $readOnly): Crawler
    {
        self::bootKernel();
        $container = static::getContainer();

        // Le rendu lit la locale de la requête courante (formats de date), et le bouton Supprimer
        // génère un jeton CSRF, stocké en session.
        $request = Request::create('/fr/mes-seances');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));
        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);
        $requestStack->push($request);

        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneByEmail('user-fixture-11-workout@test.com') ?? throw new \LogicException('Fixture user not found.');
        // `app.user` : les boutons d'action de ses propres séances le lisent.
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = $container->get(WorkoutRepository::class);
        $workout = $workoutRepository->findOneBy([
            'owner' => $user,
        ]);
        self::assertInstanceOf(Workout::class, $workout);

        /** @var Environment $twig */
        $twig = $container->get('twig');

        return new Crawler($twig->render('workout/list/_session_row.html.twig', [
            'workout' => $workout,
            'tonnage' => null,
            'muscles' => [],
            'hiddenCount' => 0,
            'exerciseCount' => 0,
            'hasPr' => false,
            'hasRepsRecord' => false,
            'badges' => [],
            'user' => $user,
            'showUrl' => '/fr/mes-seances',
            'readOnly' => $readOnly,
        ]));
    }
}
