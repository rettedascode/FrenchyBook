<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\District;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
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
            ->add('district', EnumType::class, [
                'class' => District::class,
                'label' => 'register.district',
                'help' => 'register.district_help',
                'placeholder' => 'register.district_placeholder',
                'choice_label' => static fn (District $d) => 'enum.district.'.$d->value,
            ])
            ->add('name', TextType::class, [
                'label' => 'profile.name',
                'help' => 'profile.name_help',
                'attr' => ['autocomplete' => 'username', 'autocapitalize' => 'words', 'autocorrect' => 'off', 'spellcheck' => 'false', 'maxlength' => 30],
            ])
            ->add('email', EmailType::class, [
                'label' => 'profile.email',
                'help' => 'profile.email_help',
                'attr' => ['autocomplete' => 'email', 'inputmode' => 'email', 'autocapitalize' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_token_id' => 'profile',
        ]);
    }
}
