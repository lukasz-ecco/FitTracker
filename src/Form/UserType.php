<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Imie',
                'attr' => [
                    'placeholder' => 'Podaj imię',
                ],
            ])
            ->add('surrname', null, [
                'label' => 'Nazwisko',
                'attr' => [
                    'placeholder' => 'Podaj nazwisko',
                ],
            ])
            ->add('age', NumberType::class, [
                'label' => 'Wiek',
                'attr' => [
                    'placeholder' => 'Podaj wiek',
                ],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Płeć',
                'choices' => [
                    'Mężczyzna' => 1,
                    'Kobieta' => 2,
                ],
                'attr' => [
                    'placeholder' => 'Wybierz płeć',
                ],
            ])
            ->add('height', NumberType::class, [
                'label' => 'Wzrost',
                'attr' => [
                    'placeholder' => 'Podaj wzrost',
                ],
            ])
            ->add('weight', NumberType::class, [
                'label' => 'Waga',
                'attr' => [
                    'placeholder' => 'Podaj wagę',
                ],
            ])
            ->add('profilePicture', FileType::class, [
                'label' => 'Zdjęcie profilowe',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Proszę przesłać obraz w formacie JPEG, PNG',
                    ])
                ],
                'attr' => [
                    'type' => 'file',
                    'placeholder' => 'Wybierz zdjęcie',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Zapisz zmiany',
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
