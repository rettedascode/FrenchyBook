<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\Quarter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Verwaltung: Name, E-Mail und Sprache eines Mitglieds korrigieren. */
class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'register.first_name',
                'attr' => ['autocomplete' => 'given-name', 'autocapitalize' => 'words', 'maxlength' => 50],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'register.last_name',
                'attr' => ['autocomplete' => 'family-name', 'autocapitalize' => 'words', 'maxlength' => 50],
            ])
            ->add('quarter', EnumType::class, [
                'class' => Quarter::class,
                'label' => 'register.quarter',
                'help' => 'register.quarter_help',
                'placeholder' => 'register.quarter_placeholder',
                'choice_label' => static fn (Quarter $q) => $q->label(),
                'choice_translation_domain' => false,
                // Gruppiert nach Stadtbezirk (in der Reihenfolge 1 Innenstadt … 9 Mülheim)
                'group_by' => static fn (Quarter $q) => $q->district()->label(),
                'attr' => ['data-controller' => 'searchable-select'],
            ])
            ->add('name', TextType::class, [
                'label' => 'profile.name',
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'words', 'spellcheck' => 'false', 'maxlength' => 30],
            ])
            ->add('email', EmailType::class, [
                'label' => 'profile.email',
                'attr' => ['autocomplete' => 'off', 'inputmode' => 'email', 'autocapitalize' => 'off'],
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'profile.language',
                'choices' => ['Deutsch' => 'de', 'Français' => 'fr'],
                'choice_translation_domain' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_token_id' => 'admin-user',
        ]);
    }
}
