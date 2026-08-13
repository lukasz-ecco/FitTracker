<?php

namespace App\Tests\Form;

use App\Entity\TrainingGoal;
use App\Enum\TrainingGoalType as GoalEnum;
use App\Form\TrainingGoalType;
use Symfony\Component\Form\Test\TypeTestCase;

class TrainingGoalTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'goalType' => 'muscle_gain',
            'fitnessLevel' => 2,
            'notes' => 'Budowanie bicepsa i tricepsa',
        ];

        $model = new TrainingGoal();
        $form = $this->factory->create(TrainingGoalType::class, $model);

        $expected = new TrainingGoal();
        $expected->setGoalType(GoalEnum::MUSCLE_GAIN);
        $expected->setFitnessLevel(2);
        $expected->setNotes('Budowanie bicepsa i tricepsa');

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $this->assertSame($expected->getGoalType(), $model->getGoalType());
        $this->assertSame($expected->getFitnessLevel(), $model->getFitnessLevel());
        $this->assertSame($expected->getNotes(), $model->getNotes());
    }
}
