<?php

namespace App\Controller;

use App\Entity\TrainingGoal;
use App\Entity\User;
use App\Form\TrainingGoalType;
use App\Repository\TrainingGoalRepository;
use App\Service\ExerciseSuggestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/training-goal')]
final class TrainingGoalController extends AbstractController
{
    /**
     * Ustawia nowy cel treningowy dla użytkownika.
     */
    #[Route('/set', name: 'app_training_goal_set', methods: ['GET', 'POST'])]
    public function set(
        Request $request,
        EntityManagerInterface $em,
        TrainingGoalRepository $goalRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $goal = new TrainingGoal();
        $goal->setUser($user);

        $form = $this->createForm(TrainingGoalType::class, $goal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($goal);
            $em->flush();

            $this->addFlash('success', 'Twój cel treningowy został pomyślnie zaktualizowany.');

            return $this->redirectToRoute('app_training_goal_suggestions');
        }

        $activeGoals = $goalRepo->findActiveGoalsByUser($user);

        return $this->render('training_goal/set.html.twig', [
            'form' => $form,
            'activeGoals' => $activeGoals,
        ]);
    }

    /**
     * Wyświetla sugestie ćwiczeń na podstawie aktywnych celów treningowych.
     */
    #[Route('/suggestions', name: 'app_training_goal_suggestions', methods: ['GET'])]
    public function suggestions(
        TrainingGoalRepository $goalRepo,
        ExerciseSuggestionService $suggestionService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $activeGoals = $goalRepo->findActiveGoalsByUser($user);
        $suggestions = $suggestionService->getSuggestionsForGoals($activeGoals);

        return $this->render('training_goal/suggestions.html.twig', [
            'goals' => $activeGoals,
            'suggestions' => $suggestions,
        ]);
    }
}
