<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExerciseRestoreControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    // ── Accès / Sécurité ──────────────────────────────────────────────────────

    public function testRestoreRedirectsToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $exercise = $this->archiveExercise(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(
            Request::METHOD_POST,
            $this->getRestoreUrl($exercise),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $this->assertResponseRedirects();
    }

    public function testRestoreWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->archiveExercise(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_POST, $this->getRestoreUrl($exercise));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCannotRestoreExerciseOfAnotherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $exercise = $this->archiveExercise(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(
            Request::METHOD_POST,
            $this->getRestoreUrl($exercise),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRestoreWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->archiveExercise(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(
            Request::METHOD_POST,
            $this->getRestoreUrl($exercise),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_X-CSRF-Token' => 'invalid-token',
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Restauration ──────────────────────────────────────────────────────────

    public function testRestoreClearsArchivedFlag(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->archiveExercise(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $exerciseId = $exercise->id;

        $this->sendRestoreRequest($client, $exercise);

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        /** @var array{success: bool} $data */
        $data = json_decode($content, true);
        $this->assertTrue($data['success']);

        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $reloaded = $repository->find($exerciseId);
        $this->assertNotNull($reloaded);
        $this->assertFalse($reloaded->archived);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function archiveExercise(string $name): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'name' => $name,
        ]);
        $this->assertNotNull($exercise);

        $exercise->archived = true;

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        return $exercise;
    }

    private function sendRestoreRequest(KernelBrowser $client, Exercise $exercise): void
    {
        $token = $this->getRestoreCsrfToken($client, $exercise);

        $client->request(
            Request::METHOD_POST,
            $this->getRestoreUrl($exercise),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_X-CSRF-Token' => $token,
            ],
        );
    }

    private function getRestoreCsrfToken(KernelBrowser $client, Exercise $exercise): string
    {
        $this->assertNotNull($exercise->id);

        return $this->csrfTokenFromPage(
            $client,
            '/fr/bibliotheque',
            \sprintf('div[data-exercise--restore-url-value*="%s"]', $exercise->id->toRfc4122()),
            'data-exercise--restore-csrf-token-value',
        );
    }

    private function getRestoreUrl(Exercise $exercise): string
    {
        $this->assertNotNull($exercise->id);

        return \sprintf('/fr/bibliotheque/exercice/%s/restaurer', $exercise->id->toRfc4122());
    }
}
