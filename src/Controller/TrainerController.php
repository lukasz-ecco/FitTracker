<?php

namespace App\Controller;

use App\Entity\TrainerTraineeConnection;
use App\Entity\User;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Repository\UserRepository;
use App\Service\TrainerInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trainer')]
#[IsGranted('ROLE_TRAINER')]
class TrainerController extends AbstractController
{
    #[Route('/trainees', name: 'app_trainer_trainees', methods: ['GET'])]
    public function trainees(TrainerTraineeConnectionRepository $connectionRepo): Response
    {
        /** @var User $trainer */
        $trainer = $this->getUser();
        
        $connections = $connectionRepo->findBy(['trainer' => $trainer]);

        return $this->render('trainer/trainees.html.twig', [
            'connections' => $connections,
        ]);
    }

    #[Route('/trainees/invite', name: 'app_trainer_invite', methods: ['POST'])]
    public function invite(Request $request, TrainerInvitationService $invitationService): Response
    {
        /** @var User $trainer */
        $trainer = $this->getUser();
        
        $email = $request->request->get('email');
        
        try {
            $invitationService->invite($trainer, $email);
            $this->addFlash('success', 'Wysłano zaproszenie do ' . $email);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_trainer_trainees');
    }
}
