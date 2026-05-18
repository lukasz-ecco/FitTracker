<?php

namespace App\Form;

use App\Entity\Meseurments;
use App\Entity\Muscles;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MeseurmentsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Size')
            ->add('date')
            ->add('Muscle_id', EntityType::class, [
                'class' => Muscles::class,
                'choice_label' => 'id',
            ])
            ->add('User_id', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Meseurments::class,
        ]);
    }
}
