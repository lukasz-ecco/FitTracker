<?php

namespace App\Form;

use App\Entity\Exercises;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Muscles;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
class ExerciseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nazwa ćwiczenia',
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'Poziom trudności',
                'choices' => [
                    'Początkujący' => 1,
                    'Średniozaawansowany' => 2,
                    'Zaawansowany' => 3,
                    ],
                    'placeholder' => 'Wybierz poziom',   
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Typ ćwiczenia',
                'choices' => [
                    'Izolacyjne' => 'Izolacyjne',
                    'Wielostawowe' => 'Wielostawowe',
                    ],
                'placeholder' => 'Wybierz typ',
            ])
            ->add('exerciseMuscles', CollectionType::class, [
                'entry_type' => ExerciseMuscleType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exercises::class,
        ]);
    }
}
