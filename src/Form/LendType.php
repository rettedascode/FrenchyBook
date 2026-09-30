<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * „Verleihen“: Freund antippen, optional Rückgabedatum – fertig.
 */
class LendType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('borrower', EntityType::class, [
                'label' => 'lend.borrower',
                'class' => User::class,
                'choices' => $options['friends'],
                'choice_label' => 'name',
                'choice_translation_domain' => false,
                'expanded' => true,
                'placeholder' => false,
                'constraints' => [new Assert\NotNull(message: 'loan.borrower.not_null')],
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'lend.due_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'invalid_message' => 'loan.due_at.invalid',
                'attr' => ['min' => (new \DateTimeImmutable('today'))->format('Y-m-d'), 'data-due-date-target' => 'input'],
                'constraints' => [new Assert\GreaterThanOrEqual('today', message: 'loan.due_at.past')],
            ])
            ->add('note', TextType::class, [
                'label' => 'lend.note',
                'required' => false,
                'attr' => ['placeholder' => 'lend.note_placeholder'],
                'constraints' => [new Assert\Length(max: 255, maxMessage: 'loan.note.too_long')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'friends' => [],
            'csrf_token_id' => 'lend',
        ]);
        $resolver->setAllowedTypes('friends', 'array');
    }
}
