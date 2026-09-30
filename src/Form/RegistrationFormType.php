<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\District;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'register.first_name',
                'attr' => ['autocomplete' => 'given-name', 'autocapitalize' => 'words', 'maxlength' => 50, 'data-autofocus' => true],
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
                'label' => 'register.name',
                'help' => 'register.name_help',
                'attr' => ['autocomplete' => 'username', 'autocapitalize' => 'off', 'autocorrect' => 'off', 'spellcheck' => 'false', 'maxlength' => 30],
            ])
            ->add('email', EmailType::class, [
                'label' => 'register.email',
                'attr' => ['autocomplete' => 'email', 'inputmode' => 'email', 'autocapitalize' => 'off'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'register.password',
                'help' => 'register.password_help',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'user.password.not_blank'),
                    new Assert\Length(min: 8, max: 4096, minMessage: 'user.password.too_short'),
                ],
            ]);

        if ('' !== $options['invite_code']) {
            $inviteCode = $options['invite_code'];
            $builder->add('inviteCode', TextType::class, [
                'label' => 'register.invite_code',
                'help' => 'register.invite_code_help',
                'mapped' => false,
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'off'],
                'constraints' => [
                    new Assert\NotBlank(message: 'user.invite_code.not_blank'),
                    new Assert\Callback(static function (?string $value, ExecutionContextInterface $context) use ($inviteCode): void {
                        if (null !== $value && '' !== $value && !hash_equals(mb_strtolower(trim($inviteCode)), mb_strtolower(trim($value)))) {
                            $context->buildViolation('user.invite_code.invalid')->addViolation();
                        }
                    }),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'invite_code' => '',
        ]);
        $resolver->setAllowedTypes('invite_code', 'string');
    }
}
