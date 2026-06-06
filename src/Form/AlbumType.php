<?php

namespace App\Form;

use App\Entity\Album;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlbumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'album',
                'attr' => ['placeholder' => 'FIFA World Cup Qatar 2022'],
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Éditeur',
            ])
            ->add('year', IntegerType::class, [
                'label' => 'Année',
                'required' => false,
            ])
            ->add('stickersPerPack', IntegerType::class, [
                'label' => 'Vignettes par pochette',
                'help' => 'Sert à estimer le nombre de pochettes pour compléter l\'album.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Album::class,
        ]);
    }
}
