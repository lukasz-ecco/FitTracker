<?php

namespace App\Form;

use App\Entity\ExerciseMuscle;
use App\Entity\Muscles;
use App\Enum\MuscleActivationLevel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExerciseMuscleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Muscle', EntityType::class, [
                'class' => Muscles::class,
                'choice_label' => 'name',
                'label' => false,
                'attr' => [
                    'class' => 'block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm bg-white'
                ]
            ])
            ->add('ActivationLevel', EnumType::class, [
                'class' => MuscleActivationLevel::class,
                'choice_label' => fn (MuscleActivationLevel $choice) => $choice->getLabel(),
                'label' => false,
                'placeholder' => 'Poziom aktywacji',
                'attr' => [
                    'class' => 'block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm bg-white'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExerciseMuscle::class,
        ]);
    }
}
