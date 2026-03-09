<?php

namespace App\Controller;

use App\Entity\Muscles;
use App\Form\MusclesType;
use App\Repository\MusclesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/muscles')]
final class MusclesController extends AbstractController
{
    #[Route(name: 'app_muscles_index', methods: ['GET'])]
    public function index(MusclesRepository $musclesRepository): Response
    {
        return $this->render('muscles/index.html.twig', [
            'muscles' => $musclesRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_muscles_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $muscle = new Muscles();
        $form = $this->createForm(MusclesType::class, $muscle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($muscle);
            $entityManager->flush();

            return $this->redirectToRoute('app_muscles_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('muscles/new.html.twig', [
            'muscle' => $muscle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_muscles_show', methods: ['GET'])]
    public function show(Muscles $muscle): Response
    {
        return $this->render('muscles/show.html.twig', [
            'muscle' => $muscle,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_muscles_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Muscles $muscle, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MusclesType::class, $muscle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_muscles_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('muscles/edit.html.twig', [
            'muscle' => $muscle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_muscles_delete', methods: ['POST'])]
    public function delete(Request $request, Muscles $muscle, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$muscle->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($muscle);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_muscles_index', [], Response::HTTP_SEE_OTHER);
    }
}
