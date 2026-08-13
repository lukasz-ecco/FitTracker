<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\TrainingGoal;
use App\Enum\TrainingGoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrainingGoalControllerTest extends WebTestCase
{
    private function createAndLoginUser($client): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $user = new User();
        $user->setEmail(uniqid('test_tg_', true) . '@example.com');
        $user->setPassword('testpassword');
        $user->setRoles(['ROLE_USER']);
        
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        return $user;
    }

    public function testRedirectToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/training-goal/set');
        
        // Powinno przekierować do logowania (zazwyczaj kod 302)
        $this->assertResponseRedirects('/login');
    }

    public function testSetGoalPageLoads(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $client->request('GET', '/training-goal/set');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Twoje cele treningowe');
        $this->assertSelectorExists('form[name="training_goal"]');
    }

    public function testSubmitTrainingGoalForm(): void
    {
        $client = static::createClient();
        $user = $this->createAndLoginUser($client);

        $crawler = $client->request('GET', '/training-goal/set');
        $form = $crawler->selectButton('Dodaj cel')->form([
            'training_goal[goalType]' => 'weight_loss',
            'training_goal[fitnessLevel]' => '2',
            'training_goal[notes]' => 'Zredukować tkankę tłuszczową o 5 kg',
        ]);

        $client->submit($form);

        // Powinno przekierować do sugerowanych ćwiczeń
        $this->assertResponseRedirects('/training-goal/suggestions');

        // Sprawdzamy czy cel został zapisany w bazie i czy jest aktywny
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedGoals = $entityManager->getRepository(TrainingGoal::class)->findActiveGoalsByUser($user);

        $this->assertCount(1, $savedGoals);
        $savedGoal = $savedGoals[0];
        $this->assertSame(TrainingGoalType::WEIGHT_LOSS, $savedGoal->getGoalType());
        $this->assertSame(2, $savedGoal->getFitnessLevel());
        $this->assertSame('Zredukować tkankę tłuszczową o 5 kg', $savedGoal->getNotes());
        $this->assertTrue($savedGoal->isActive());
    }

    public function testSuggestionsWithoutGoalShowsCTA(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $client->request('GET', '/training-goal/suggestions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Brak aktywnych celów');
        $this->assertSelectorExists('a[href="/training-goal/set"]');
    }
}
