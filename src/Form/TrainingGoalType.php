<?php

namespace App\Form;

use App\Entity\GoalType;
use App\Entity\TrainingGoal;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrainingGoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goalType', EntityType::class, [
                'class' => GoalType::class,
                'choice_label' => 'label',
                'label' => 'Cel treningowy',
                'placeholder' => 'Wybierz...',
                'row_attr' => ['class' => 'mb-4'],
            ])
            ->add('fitnessLevel', ChoiceType::class, [
                'choices' => [
                    'Początkujący' => 1,
                    'Średniozaawansowany' => 2,
                    'Zaawansowany' => 3,
                ],
                'label' => 'Poziom zaawansowania',
                'placeholder' => 'Wybierz...',
                'row_attr' => ['class' => 'mb-4'],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Notatka (opcjonalnie)',
                'row_attr' => ['class' => 'mb-4'],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Dodaj dodatkowe informacje o swoich celach...'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrainingGoal::class,
        ]);
    }
}
