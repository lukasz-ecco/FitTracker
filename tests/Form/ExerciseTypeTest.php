<?php

namespace App\Tests\Form;

use App\Entity\Exercises;
use App\Form\ExerciseType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class ExerciseTypeTest extends KernelTestCase
{
    public function testSubmitValidData(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $exercise = new Exercises();
        $form = $formFactory->create(ExerciseType::class, $exercise, [
            'csrf_protection' => false,
        ]);

        $formData = [
            'name' => 'Wyciskanie testowe',
            'difficulty' => 1,
            'type' => 'Wielostawowe',
        ];

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        
        if (!$form->isValid()) {
            dump((string) $form->getErrors(true, false));
        }

        $this->assertTrue($form->isValid());

        $this->assertEquals('Wyciskanie testowe', $exercise->getName());
        $this->assertEquals(1, $exercise->getDifficulty());
        $this->assertEquals('Wielostawowe', $exercise->getType());
    }
}
