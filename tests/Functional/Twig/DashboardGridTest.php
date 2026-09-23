<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Repository\UserRepository;
use App\Service\Dashboard\DashboardState;
use App\Service\Dashboard\DashboardViewData;
use App\Service\Dashboard\DashboardViewDataBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Cas limite propre à la lecture seule : le propriétaire a masqué tous ses widgets alors que
 * Régularité est encore verrouillée. Sur son propre dashboard la carte "verrouillé" occupe l'espace ;
 * un visiteur ne la voit jamais, la grille resterait donc blanche sans message dédié.
 */
final class DashboardGridTest extends KernelTestCase
{
    private const string OWNER = 'user-fixture-26-workout@test.com';

    // Le rendu hors requête HTTP se fait dans la locale par défaut de l'application (en).
    private const string LOCKED_CARD_TEXT = '1 more workout';

    private const string NO_VISIBLE_WIDGETS_TEXT = 'has decided not to display any data';

    public function testOwnerWhoHidEverythingWhileRegularityIsLockedSeesTheLockedCard(): void
    {
        [$owner, $data] = $this->buildDataWithEveryWidgetHidden();

        $html = $this->renderGrid($data, $owner, readOnly: false);

        self::assertStringContainsString(self::LOCKED_CARD_TEXT, $html);
        self::assertStringNotContainsString(self::NO_VISIBLE_WIDGETS_TEXT, $html);
    }

    public function testVisitorSeesTheNeutralMessageAndNeverTheLockedCard(): void
    {
        [$owner, $data] = $this->buildDataWithEveryWidgetHidden();

        $html = $this->renderGrid($data, $owner, readOnly: true);

        self::assertStringContainsString(self::NO_VISIBLE_WIDGETS_TEXT, $html);
        self::assertStringNotContainsString(self::LOCKED_CARD_TEXT, $html);
    }

    /**
     * @return array{User, DashboardViewData}
     */
    private function buildDataWithEveryWidgetHidden(): array
    {
        self::bootKernel();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneByEmail(self::OWNER) ?? throw new \LogicException('Fixture user not found.');
        $owner->hiddenWidgets = array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, DashboardWidgetEnum::cases());

        /** @var DashboardViewDataBuilder $builder */
        $builder = static::getContainer()->get(DashboardViewDataBuilder::class);

        return [$owner, $builder->build($owner, $owner, new DashboardState(1))];
    }

    private function renderGrid(DashboardViewData $data, User $owner, bool $readOnly): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->render('dashboard/_dashboard_grid.html.twig', [
            'data' => $data,
            'subject' => $owner,
            'readOnly' => $readOnly,
            'badgesUrl' => null,
        ]);
    }
}
