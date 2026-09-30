<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** „Passwort vergessen“ – Schritt 2: neues Passwort festlegen. */
class NewPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', PasswordType::class, [
            'label' => 'profile.password.new',
            'help' => 'register.password_help',
            'attr' => ['autocomplete' => 'new-password', 'data-autofocus' => true],
            'constraints' => [
                new Assert\NotBlank(message: 'user.password.new_not_blank'),
                new Assert\Length(min: 8, max: 4096, minMessage: 'user.password.too_short'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'reset_password']);
    }
}
