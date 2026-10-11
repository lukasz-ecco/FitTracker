<?php

namespace App\Tests\Entity;

use App\Entity\BodyParts;
use App\Entity\Muscles;
use PHPUnit\Framework\TestCase;

class BodyPartsTest extends TestCase
{
    public function testInitialValues(): void
    {
        $bodyPart = new BodyParts();

        $this->assertNull($bodyPart->getId());
        $this->assertNull($bodyPart->getName());
        $this->assertSame(100, $bodyPart->getSortOrder());
        $this->assertTrue($bodyPart->isMeasurable());
        $this->assertCount(0, $bodyPart->getMuscles());
    }

    public function testSettersAndGetters(): void
    {
        $bodyPart = new BodyParts();

        $bodyPart->setName('Klatka Piersiowa');
        $this->assertSame('Klatka Piersiowa', $bodyPart->getName());

        $bodyPart->setSortOrder(20);
        $this->assertSame(20, $bodyPart->getSortOrder());

        $bodyPart->setIsMeasurable(false);
        $this->assertFalse($bodyPart->isMeasurable());
    }

    public function testMuscleRelations(): void
    {
        $bodyPart = new BodyParts();
        $muscle = new Muscles();
        $muscle->setName('Piersiowy Większy');

        $bodyPart->addMuscle($muscle);
        $this->assertCount(1, $bodyPart->getMuscles());
        $this->assertSame($bodyPart, $muscle->getBodyPart());

        $bodyPart->removeMuscle($muscle);
        $this->assertCount(0, $bodyPart->getMuscles());
        $this->assertNull($muscle->getBodyPart());
    }
}
