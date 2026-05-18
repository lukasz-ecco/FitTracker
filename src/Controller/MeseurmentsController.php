<?php

namespace App\Controller;

use App\Entity\Meseurments;
use App\Form\MeseurmentsType;
use App\Repository\MeseurmentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/meseurments')]
final class MeseurmentsController extends AbstractController
{
    #[Route(name: 'app_meseurments_index', methods: ['GET'])]
    public function index(MeseurmentsRepository $meseurmentsRepository): Response
    {
        return $this->render('meseurments/index.html.twig', [
            'meseurments' => $meseurmentsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_meseurments_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $meseurment = new Meseurments();
        $form = $this->createForm(MeseurmentsType::class, $meseurment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($meseurment);
            $entityManager->flush();

            return $this->redirectToRoute('app_meseurments_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('meseurments/new.html.twig', [
            'meseurment' => $meseurment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_meseurments_show', methods: ['GET'])]
    public function show(int $id, MeseurmentsRepository $meseurmentsRepository): Response
    {
        $meseurment = $meseurmentsRepository->findByUserId($id);

        if (!$meseurment) {
            throw $this->createNotFoundException('No meseurment found for id '.$id);
        }

        return $this->render('meseurments/show.html.twig', [
            'meseurment' => $meseurment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_meseurments_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Meseurments $meseurment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MeseurmentsType::class, $meseurment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_meseurments_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('meseurments/edit.html.twig', [
            'meseurment' => $meseurment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_meseurments_delete', methods: ['POST'])]
    public function delete(Request $request, Meseurments $meseurment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$meseurment->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($meseurment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_meseurments_index', [], Response::HTTP_SEE_OTHER);
    }
}
