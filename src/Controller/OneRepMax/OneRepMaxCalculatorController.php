<?php

declare(strict_types=1);

namespace App\Controller\OneRepMax;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\OneRepMax\OneRepMaxGuideLinks;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Calculateur de 1RM public, comme les fiches exercices : il fait découvrir le site depuis les
 * moteurs de recherche. Le calcul se fait dans le navigateur (one_rep_max/calculator_controller.js).
 */
final class OneRepMaxCalculatorController extends AbstractController
{
    public const string ROUTE = 'app_one_rep_max_calculator';

    /**
     * Série d'exemple affichée à l'ouverture, pour montrer un résultat sans rien saisir.
     */
    private const array EXAMPLE_WEIGHTS = [
        'kg' => 80,
        'lbs' => 175,
    ];

    private const int EXAMPLE_REPS = 6;

    public function __construct(
        private readonly OneRepMaxGuideLinks $guideLinks,
    ) {
    }

    #[Route(path: [
        'en' => '/one-rep-max-calculator',
        'fr' => '/calculateur-1rm',
        'it' => '/calcolatore-1rm',
        'es' => '/calculadora-1rm',
        'pt' => '/calculadora-1rm',
        'de' => '/1rm-rechner',
        'nl' => '/1rm-calculator',
        'pl' => '/kalkulator-1rm',
    ], name: self::ROUTE, methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        $unit = $user instanceof User ? $user->unitOfMeasure : UnitOfMeasureEnum::KG;

        return $this->render('one_rep_max/index.html.twig', [
            'unit' => $unit,
            'units' => UnitOfMeasureEnum::cases(),
            'exampleWeight' => self::EXAMPLE_WEIGHTS[$unit->value],
            'exampleReps' => self::EXAMPLE_REPS,
            'guideLinks' => $this->guideLinks->build($request->getLocale()),
            'locales' => LocaleAllowedEnum::cases(),
            'currentLocale' => LocaleAllowedEnum::from($request->getLocale()),
            'isLoggedIn' => null !== $user,
        ]);
    }
}
