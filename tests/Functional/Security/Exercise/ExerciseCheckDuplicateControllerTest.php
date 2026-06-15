<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ExerciseCheckDuplicateControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER_REVERSE_FLY = UserFixtures::USER_REVERSE_FLY;

    private const string USER_TIRAGE_SUPINATION = UserFixtures::USER_TIRAGE_SUPINATION;

    private const string URL = '/fr/bibliotheque/exercice/verifier-doublon';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, self::URL, [
            'name' => 'Test',
        ]);

        $this->assertResponseRedirects('/fr/');
    }

    public function testResponseIsJson(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $client->request(Request::METHOD_GET, self::URL, [
            'name' => 'test',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testReturnsNullTypeWhenNoMatch(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, 'Exercice inexistant xyz');

        $this->assertNull($data['type']);
    }

    public function testReturnsNullTypeWhenNameIsEmpty(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, '');

        $this->assertNull($data['type']);
    }

    public function testDetectsCustomDuplicateForReverseFlyUser(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertSame('custom', $data['type']);
        $this->assertSame(ExerciseFixtures::EXERCISE_REVERSE_FLY, $data['name']);
    }

    public function testDetectsCustomDuplicateForTirageSupinationUser(): void
    {
        $client = $this->login(self::USER_TIRAGE_SUPINATION);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);

        $this->assertSame('custom', $data['type']);
        $this->assertSame(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION, $data['name']);
    }

    public function testCustomDuplicateReturnsDate(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertArrayHasKey('date', $data);
        $this->assertNotEmpty($data['date']);
    }

    public function testCustomDuplicateIsCaseInsensitive(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, mb_strtolower(ExerciseFixtures::EXERCISE_REVERSE_FLY));

        $this->assertSame('custom', $data['type']);
    }

    public function testCustomDuplicateIsIsolatedToOwner(): void
    {
        // USER_REVERSE_FLY cherche "Tirage supination" — exercice de l'autre user
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);

        $this->assertNull($data['type']);
    }

    public function testTirageSupinationUserDoesNotSeeReverseFly(): void
    {
        // Symétrique : USER_TIRAGE_SUPINATION cherche "Reverse fly"
        $client = $this->login(self::USER_TIRAGE_SUPINATION);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertNull($data['type']);
    }

    public function testDescriptionDoesNotLeakInResponse(): void
    {
        // L'exercice Tirage supination a une description — elle ne doit pas apparaître dans la réponse
        $client = $this->login(self::USER_TIRAGE_SUPINATION);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);

        $this->assertSame('custom', $data['type']);
        $this->assertArrayNotHasKey('description', $data);
    }

    public function testDetectsPublicDuplicate(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, 'Leg extension');

        $this->assertSame('public', $data['type']);
        $this->assertArrayHasKey('name', $data);
    }

    public function testPublicDuplicateIsCaseInsensitive(): void
    {
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, 'LEG EXTENSION');

        $this->assertSame('public', $data['type']);
    }

    public function testCustomTakesPriorityOverPublic(): void
    {
        // Si un user a un exercice custom avec le même nom qu'un exercice public,
        // le type retourné doit être 'custom'
        $client = $this->login(self::USER_REVERSE_FLY);
        $data = $this->requestDuplicate($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertSame('custom', $data['type']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestDuplicate(KernelBrowser $client, string $name): array
    {
        $client->request(Request::METHOD_GET, self::URL, [
            'name' => $name,
        ]);

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);

        $data = json_decode($content, associative: true);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }
}
