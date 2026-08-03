<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\EventListener\Form\WorkoutFormListener;
use App\Repository\RoutineRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Workout>
 */
final class WorkoutType extends AbstractType
{
    private const int MIN_DURATION = 0;

    private const int STEP_DURATION = 1;

    private const string FIELD_CLASS = 'w-full bg-white/[0.04] border border-white/[0.07] rounded-xl px-3.5 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none focus:border-[#f43f5e] focus:bg-white/[0.06] transition-all';

    private const string LABEL_CLASS = 'block text-xs font-semibold text-slate-500 mb-2';

    private const string ROW_CLASS = 'flex flex-col';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];
        $workout = $builder->getData();

        $builder
            ->add('performedAt', DateType::class, $this->performedAtOptions($isEdit, $isEdit && $workout instanceof Workout ? $workout : null))
            ->add('duration', IntegerType::class, $this->durationOptions())
            ->add('routine', EntityType::class, $this->routineOptions())
            ->add('workoutExercises', CollectionType::class, $this->workoutExercisesOptions())
            ->add('note', $isEdit ? TextareaType::class : HiddenType::class, $this->noteOptions($isEdit));

        $builder->addEventSubscriber(new WorkoutFormListener());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workout::class,
            'is_edit' => false,
            'csrf_token_id' => 'workout',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtOptions(bool $isEdit, ?Workout $workout): array
    {
        return [
            'widget' => 'single_text',
            'html5' => false,
            'format' => 'yyyy-MM-dd',
            'input' => 'datetime_immutable',
            'label' => $this->translator->trans('workout.date', [], 'navigation'),
            'attr' => $isEdit && null !== $workout ? $this->performedAtEditAttr($workout) : $this->performedAtCreateAttr(),
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
            'row_attr' => [
                'class' => self::ROW_CLASS,
            ],
            /**
             * Validé sur la donnée transformée du champ (avant écriture dans l'entité) plutôt que
             * de compter sur `Workout::$performedAt` — son setter ignore silencieusement un `null`
             * (garde défensive pensée pour un champ optionnel, ce que la date n'est pas), donc
             * `Assert\NotBlank` au niveau entité ne voit jamais de valeur vide.
             */
            'constraints' => [
                new NotBlank(message: 'workout.performed_at.required'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtEditAttr(Workout $workout): array
    {
        return [
            ...$this->datePickerAttr(),
            ...$this->dateDuplicateCheckAttr(),
            'class' => self::FIELD_CLASS,
            'data-date-exclude-id-value' => (string) $workout->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtCreateAttr(): array
    {
        return [
            ...$this->datePickerAttr(),
            ...$this->dateDuplicateCheckAttr(),
        ];
    }

    /**
     * Vérif doublon de date (controller `date`) — en édition, `data-date-exclude-id-value`
     * (ajouté par `performedAtEditAttr()`) exclut la séance en cours d'édition du comptage, sinon
     * sa propre date serait systématiquement signalée comme doublon.
     *
     * @return array<string, mixed>
     */
    private function dateDuplicateCheckAttr(): array
    {
        return [
            'data-controller' => 'date workout--date-picker',
            'data-date-check-url-value' => $this->urlGenerator->generate('workout_check_date'),
            'data-date-message-value' => $this->translator->trans('workout.check_date.message', [
                'count' => '__COUNT__',
            ], 'navigation'),
            'data-date-confirm-button-value' => $this->translator->trans('workout.check_date.confirm', [], 'navigation'),
        ];
    }

    /**
     * Attributs communs création/édition du calendrier — la clé `data-controller` de ce tableau
     * est écrasée par `dateDuplicateCheckAttr()` pour y ajouter le controller `date`.
     *
     * @return array<string, mixed>
     */
    private function datePickerAttr(): array
    {
        return [
            'placeholder' => $this->translator->trans('workout.date_placeholder', [], 'navigation'),
            'data-controller' => 'workout--date-picker',
            'data-workout--date-picker-locale-value' => $this->currentLocale(),
        ];
    }

    private function currentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Building the workout form requires an active HTTP request.');
        }

        return $request->getLocale();
    }

    /**
     * @return array<string, mixed>
     */
    private function durationOptions(): array
    {
        return [
            'label' => $this->translator->trans('workout.duration_optional', [], 'navigation'),
            'required' => false,
            'attr' => [
                'placeholder' => $this->translator->trans('workout.duration_placeholder', [], 'navigation'),
                'min' => self::MIN_DURATION,
                'step' => self::STEP_DURATION,
                'class' => self::FIELD_CLASS . ' pr-12',
            ],
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
            'row_attr' => [
                'class' => self::ROW_CLASS,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routineOptions(): array
    {
        return [
            'class' => Routine::class,
            'label' => $this->translator->trans('workout.routine.title', [], 'navigation'),
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => $this->translator->trans('workout.routine.select', [], 'navigation'),
            'query_builder' => fn (RoutineRepository $repository): QueryBuilder => $this->routinesForCurrentUserQueryBuilder($repository),
            'attr' => [
                'id' => 'workout-routine',
                'data-action' => 'change->workout--routine-loader#onChange',
                'data-workout--routine-loader-target' => 'select',
            ],
        ];
    }

    private function routinesForCurrentUserQueryBuilder(RoutineRepository $repository): QueryBuilder
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $repository->createQueryBuilder('r')
            ->where('r.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('r.name', 'ASC');
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutExercisesOptions(): array
    {
        return [
            'entry_type' => \App\Form\WorkoutExerciseType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteOptions(bool $isEdit): array
    {
        return [
            'required' => false,
            'label' => false,
            'attr' => $isEdit ? $this->noteEditAttr() : $this->noteCreateAttr(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteEditAttr(): array
    {
        return [
            'class' => 'w-full bg-white/[0.03] border border-white/[0.07] rounded-xl px-4 py-3.5 text-[#f0f4ff] font-dm-sans text-[13.5px] leading-relaxed min-h-[100px] resize-y outline-none focus:border-[#f43f5e] transition-all',
            'placeholder' => $this->translator->trans('workout.note.placeholder', [], 'navigation'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteCreateAttr(): array
    {
        return [
            'id' => 'workout-note',
            'rows' => 4,
        ];
    }
}
