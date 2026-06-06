<?php

namespace App\Form;

use App\Entity\Sticker;
use App\Enum\StickerRarity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StickerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', TextType::class, ['label' => 'Identifiant'])
            ->add('team', TextType::class, ['label' => 'Équipe / groupe', 'required' => false])
            ->add('rarity', EnumType::class, [
                'label' => 'Type',
                'class' => StickerRarity::class,
                'choice_label' => fn (StickerRarity $r) => $r->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sticker::class,
        ]);
    }
}
