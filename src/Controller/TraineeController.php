<?php

namespace App\Controller;

use App\Entity\TrainerTraineeConnection;
use App\Entity\User;
use App\Repository\TrainerTraineeConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trainee')]
#[IsGranted('ROLE_TRAINEE')]
class TraineeController extends AbstractController
{
    #[Route('/trainers', name: 'app_trainee_trainers', methods: ['GET'])]
    public function trainers(TrainerTraineeConnectionRepository $connectionRepo): Response
    {
        /** @var User $trainee */
        $trainee = $this->getUser();
        
        $connections = $connectionRepo->findBy(['trainee' => $trainee]);

        return $this->render('trainee/trainers.html.twig', [
            'connections' => $connections,
        ]);
    }

    #[Route('/trainers/connection/{id}/status', name: 'app_trainee_connection_status', methods: ['POST'])]
    public function updateStatus(TrainerTraineeConnection $connection, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $trainee */
        $trainee = $this->getUser();
        
        if ($connection->getTrainee() !== $trainee) {
            throw $this->createAccessDeniedException('Brak dostępu.');
        }

        $status = $request->request->get('status');
        if (in_array($status, ['ACCEPTED', 'REJECTED'])) {
            $connection->setStatus($status);
            $em->flush();
            $this->addFlash('success', 'Zaktualizowano status zaproszenia.');
        }

        return $this->redirectToRoute('app_trainee_trainers');
    }

    #[Route('/trainers/connection/{id}/main', name: 'app_trainee_connection_main', methods: ['POST'])]
    public function setMain(TrainerTraineeConnection $connection, TrainerTraineeConnectionRepository $connectionRepo, EntityManagerInterface $em): Response
    {
        /** @var User $trainee */
        $trainee = $this->getUser();
        
        if ($connection->getTrainee() !== $trainee || $connection->getStatus() !== 'ACCEPTED') {
            throw $this->createAccessDeniedException('Brak dostępu lub zaproszenie niezaakceptowane.');
        }

        // Ustaw pozostałe jako nie-główne
        $allConnections = $connectionRepo->findBy(['trainee' => $trainee]);
        foreach ($allConnections as $conn) {
            $conn->setIsMain(false);
        }

        $connection->setIsMain(true);
        $em->flush();
        
        $this->addFlash('success', 'Zmieniono trenera głównego.');

        return $this->redirectToRoute('app_trainee_trainers');
    }
}
