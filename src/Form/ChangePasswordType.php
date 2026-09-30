<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'profile.password.current',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'user.password.current_not_blank'),
                    new UserPassword(message: 'user.password.current_wrong'),
                ],
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'profile.password.new',
                'help' => 'register.password_help',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'user.password.new_not_blank'),
                    new Assert\Length(min: 8, max: 4096, minMessage: 'user.password.too_short'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'change_password']);
    }
}
