<?php

namespace App\Tests\Controller\Api;

use App\Entity\Exercises;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExerciseControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createAndLoginUser($client): User
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('user_ex_' . uniqid() . '@example.com');
        $user->setPassword('password123');
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $user;
    }

    public function testGetExercisesRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/exercises');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetExercisesListAndSearch(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $this->em->createQuery('DELETE FROM App\Entity\WorkoutExerciseSet')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\WorkoutExercise')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Workout')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ExerciseMuscle')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ExerciseSupportedGoal')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Exercises')->execute();

        $ex1 = new Exercises();
        $ex1->setName('Wyciskanie sztangi');
        $ex1->setDifficulty(2);
        $ex1->setType('Wielostawowe');
        $ex1->setGifUrl('/uploads/exercise-gifs/0025.gif');
        $this->em->persist($ex1);

        $ex2 = new Exercises();
        $ex2->setName('Przysiad ze sztangą');
        $ex2->setDifficulty(3);
        $ex2->setType('Wielostawowe');
        $this->em->persist($ex2);

        $ex3 = new Exercises();
        $ex3->setName('Wyciskanie żołnierskie');
        $ex3->setDifficulty(2);
        $ex3->setType('Wielostawowe');
        $this->em->persist($ex3);

        $this->em->flush();

        // 1. Get all exercises
        $client->request('GET', '/api/exercises');
        $this->assertResponseIsSuccessful();

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertCount(3, $content);

        $ex1Item = current(array_filter($content, fn($e) => $e['name'] === 'Wyciskanie sztangi'));
        $this->assertNotEmpty($ex1Item);
        $this->assertSame('/uploads/exercise-gifs/0025.gif', $ex1Item['gifUrl']);

        // 2. Search 'wyciskanie'
        $client->request('GET', '/api/exercises', ['search' => 'wyciskanie']);
        $this->assertResponseIsSuccessful();

        $searchContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $searchContent);
        $names = array_column($searchContent, 'name');
        $this->assertContains('Wyciskanie sztangi', $names);
        $this->assertContains('Wyciskanie żołnierskie', $names);
        $this->assertNotContains('Przysiad ze sztangą', $names);

        // 3. Pagination limit
        $client->request('GET', '/api/exercises', ['page' => 1, 'limit' => 1]);
        $this->assertResponseIsSuccessful();

        $pageContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $pageContent);
    }

    public function testGetExerciseShowSuccessful(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $bodyPart = new \App\Entity\BodyParts();
        $bodyPart->setName('Klatka piersiowa');
        $this->em->persist($bodyPart);

        $muscle = new \App\Entity\Muscles();
        $muscle->setName('Mięsień piersiowy większy');
        $muscle->setBodyPart($bodyPart);
        $this->em->persist($muscle);

        $goalType = $this->em->getRepository(\App\Entity\GoalType::class)->findOneBy(['name' => 'muscle_gain']);
        if (!$goalType) {
            $goalType = new \App\Entity\GoalType();
            $goalType->setName('muscle_gain');
            $goalType->setLabel('Budowa masy');
            $this->em->persist($goalType);
        }

        $exercise = new Exercises();
        $exercise->setName('Wyciskanie na ławce prostej');
        $exercise->setDifficulty(3);
        $exercise->setType('Wielostawowe');
        $exercise->setGifUrl('/uploads/exercise-gifs/0025.gif');
        $exercise->setDescription('Połóż się na ławce, opuść sztangę do klatki i wyciśnij w górę.');
        $this->em->persist($exercise);

        $exerciseMuscle = new \App\Entity\ExerciseMuscle();
        $exerciseMuscle->setMuscle($muscle);
        $exerciseMuscle->setActivationLevel(\App\Enum\MuscleActivationLevel::HIGH);
        $exercise->addExerciseMuscle($exerciseMuscle);
        $this->em->persist($exerciseMuscle);

        $exerciseGoal = new \App\Entity\ExerciseSupportedGoal();
        $exerciseGoal->setGoalType($goalType);
        $exercise->addSupportedGoal($exerciseGoal);
        $this->em->persist($exerciseGoal);

        $this->em->flush();
        $exerciseId = $exercise->getId();
        $this->em->clear();

        $client->request('GET', '/api/exercises/' . $exerciseId);
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($exerciseId, $data['id']);
        $this->assertSame('Wyciskanie na ławce prostej', $data['name']);
        $this->assertSame('/uploads/exercise-gifs/0025.gif', $data['gifUrl']);
        $this->assertSame('Połóż się na ławce, opuść sztangę do klatki i wyciśnij w górę.', $data['description']);
        
        $this->assertNotEmpty($data['muscles']);
        $this->assertSame('Mięsień piersiowy większy', $data['muscles'][0]['name']);
        $this->assertSame('Klatka piersiowa', $data['muscles'][0]['bodyPart']);
        $this->assertSame('high', $data['muscles'][0]['activationLevel']);
        $this->assertSame('Wysoki', $data['muscles'][0]['activationLabel']);

        $this->assertNotEmpty($data['supportedGoals']);
        $this->assertSame('muscle_gain', $data['supportedGoals'][0]['name']);
        $this->assertSame('Budowa masy', $data['supportedGoals'][0]['label']);
    }

    public function testGetExerciseShowNotFound(): void
    {
        $client = static::createClient();
        $this->createAndLoginUser($client);

        $client->request('GET', '/api/exercises/99999999');
        $this->assertResponseStatusCodeSame(404);
    }
}
