<?php

namespace App\Controller;

use App\Entity\BodyParts;
use App\Entity\Meseurments;
use App\Entity\User;
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
    #[Route('/', name: 'app_meseurments_show', methods: ['GET'])]
    public function show(MeseurmentsRepository $meseurmentsRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $meseurment = $meseurmentsRepository->findByUserId($user->getId());
    
        return $this->render('meseurments/show.html.twig', [
            'meseurment' => $meseurment,
        ]);
    }
    
    #[Route('/new', name: 'app_meseurments_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $meseurment = new Meseurments();
        if ($request->query->has('id_body_part')) {
            $meseurment->setBodyPart($entityManager->getReference(BodyParts::class, $request->query->getInt('id_body_part')));
        }

        $form = $this->createForm(MeseurmentsType::class, $meseurment, [
            'action' => $this->generateUrl('app_meseurments_new', [
                'id_body_part' => $request->query->get('id_body_part')
            ])
        ]);
        $form->handleRequest($request);
        
        $user = $this->getUser();
        if ($form->isSubmitted() && $form->isValid()) {
            $meseurment->setUser($user);
            $entityManager->persist($meseurment);
            $entityManager->flush();

            return $this->redirectToRoute('app_meseurments_show', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('meseurments/_new_form.html.twig', [
                'meseurment' => $meseurment,
                'form' => $form,
            ]);
        }

        return $this->render('meseurments/new.html.twig', [
            'meseurment' => $meseurment,
            'form' => $form,
        ]);
    }


    #[Route('/{id}/edit', name: 'app_meseurments_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Meseurments $meseurment, EntityManagerInterface $entityManager): Response
    {
        if ($meseurment->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Nie masz dostępu do tego pomiaru.');
        }

        $form = $this->createForm(MeseurmentsType::class, $meseurment, [
            'action' => $this->generateUrl('app_meseurments_edit', ['id' => $meseurment->getId()])
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_meseurments_show', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('meseurments/_edit_form.html.twig', [
                'meseurment' => $meseurment,
                'form' => $form,
            ]);
        }

        return $this->render('meseurments/edit.html.twig', [
            'meseurment' => $meseurment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_meseurments_delete', methods: ['POST'])]
    public function delete(Request $request, Meseurments $meseurment, EntityManagerInterface $entityManager): Response
    {
        if ($meseurment->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Nie masz dostępu do tego pomiaru.');
        }

        if ($this->isCsrfTokenValid('delete'.$meseurment->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($meseurment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_meseurments_show', [], Response::HTTP_SEE_OTHER);
    }
}
