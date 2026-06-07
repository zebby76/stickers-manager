<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new NotBlank(message: 'Saisis un mot de passe.'),
                    new Length(
                        min: 8,
                        minMessage: 'Ton mot de passe doit faire au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ],
            'second_options' => ['label' => 'Confirme le mot de passe'],
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
