<?php

declare(strict_types=1);

namespace App\EventListener\Form;

use App\Form\WorkoutExerciseType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final readonly class WorkoutFormListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();

        if (! is_array($data) || empty($data['workoutExercises']) || ! is_array($data['workoutExercises'])) {
            return;
        }
        $form = $event->getForm();

        foreach ($data['workoutExercises'] as $index => $exerciseData) {
            $form->get('workoutExercises')->add((string) $index, WorkoutExerciseType::class);
        }
    }
}
