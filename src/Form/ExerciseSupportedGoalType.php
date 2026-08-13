<?php

namespace App\Form;

use App\Entity\ExerciseSupportedGoal;
use App\Entity\GoalType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExerciseSupportedGoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goalType', EntityType::class, [
                'class' => GoalType::class,
                'choice_label' => 'label',
                'label' => false,
                'placeholder' => 'Wybierz cel',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExerciseSupportedGoal::class,
        ]);
    }
}
