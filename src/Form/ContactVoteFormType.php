<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollOption;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<null>
 */
final class ContactVoteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ContactBroadcast $broadcast */
        $broadcast = $options['broadcast'];

        $builder
            ->add('option', EntityType::class, [
                'class' => ContactPollOption::class,
                'choices' => $broadcast->pollOptions,
                'choice_label' => 'label',
                'expanded' => true,
                'multiple' => false,
                'label' => false,
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'contact_vote',
        ]);
        $resolver->setRequired('broadcast');
        $resolver->setAllowedTypes('broadcast', ContactBroadcast::class);
    }
}
