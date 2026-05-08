<?php

namespace App\Controller;

use App\Entity\Exercises;
use App\Form\ExerciseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/exercises')]
final class ExercisesController extends AbstractController
{
    #[Route(name: 'app_exercises_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $exercises = $entityManager->getRepository(Exercises::class)->findAll();

        return $this->render('exercises/index.html.twig', [
            'controller_name' => 'ExercisesController',
            'exercises' => $exercises,
        ]);
    }

    #[Route('add', name: 'app_exercises_new')]
    public function addExercise(Request $request, EntityManagerInterface $entityManager): Response
    {
        $exercise = new Exercises();
        $form = $this->createForm(ExerciseType::class, $exercise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($exercise);
            $entityManager->flush();

            return $this->redirectToRoute('app_exercises_index');
        }

        return $this->render('exercises/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('{id}/edit', name: 'app_exercises_edit')]
    public function editExercise(Exercises $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ExerciseType::class, $id);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_exercises_index');
        }

        return $this->render('exercises/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
