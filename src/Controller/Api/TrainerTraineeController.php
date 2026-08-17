<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\TrainerTraineeConnection;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Service\TrainerInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/connections')]
#[IsGranted('ROLE_USER')]
class TrainerTraineeController extends AbstractController
{
    #[Route('', name: 'api_connections_get', methods: ['GET'])]
    public function index(TrainerTraineeConnectionRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $isTrainer = in_array('ROLE_TRAINER', $user->getRoles());
        
        if ($isTrainer) {
            $connections = $repository->findBy(['trainer' => $user]);
        } else {
            $connections = $repository->findBy(['trainee' => $user]);
        }

        return $this->json($connections, Response::HTTP_OK, [], ['groups' => ['connection:read']]);
    }

    #[Route('/invite', name: 'api_connections_invite', methods: ['POST'])]
    public function invite(Request $request, TrainerInvitationService $invitationService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!in_array('ROLE_TRAINER', $user->getRoles())) {
            return new JsonResponse(['error' => 'Tylko trener może wysyłać zaproszenia.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['email'])) {
            return new JsonResponse(['error' => 'Adres email jest wymagany.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $invitationService->invite($user, $data['email']);
            return new JsonResponse(['message' => 'Zaproszenie wysłane pomyślnie.'], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/accept', name: 'api_connections_accept', methods: ['POST'])]
    public function accept(TrainerTraineeConnection $connection, EntityManagerInterface $em): JsonResponse
    {
        if ($connection->getTrainee() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        if ($connection->getStatus() !== 'PENDING') {
            return new JsonResponse(['error' => 'Można zaakceptować tylko oczekujące zaproszenia.'], Response::HTTP_BAD_REQUEST);
        }

        $connection->setStatus('ACCEPTED');
        $em->flush();

        return new JsonResponse(['message' => 'Zaproszenie zaakceptowane.']);
    }

    #[Route('/{id}/reject', name: 'api_connections_reject', methods: ['POST'])]
    public function reject(TrainerTraineeConnection $connection, EntityManagerInterface $em): JsonResponse
    {
        if ($connection->getTrainee() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        if ($connection->getStatus() !== 'PENDING') {
            return new JsonResponse(['error' => 'Można odrzucić tylko oczekujące zaproszenia.'], Response::HTTP_BAD_REQUEST);
        }

        $connection->setStatus('REJECTED');
        $em->flush();

        return new JsonResponse(['message' => 'Zaproszenie odrzucone.']);
    }

    #[Route('/{id}/set-main', name: 'api_connections_set_main', methods: ['POST'])]
    public function setMain(TrainerTraineeConnection $connection, EntityManagerInterface $em, TrainerTraineeConnectionRepository $repository): JsonResponse
    {
        if ($connection->getTrainee() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        if ($connection->getStatus() !== 'ACCEPTED') {
            return new JsonResponse(['error' => 'Trener musi być zaakceptowany.'], Response::HTTP_BAD_REQUEST);
        }

        // Reset all other connections to isMain = false
        $allUserConnections = $repository->findBy(['trainee' => $this->getUser(), 'isMain' => true]);
        foreach ($allUserConnections as $c) {
            $c->setIsMain(false);
        }

        $connection->setIsMain(true);
        $em->flush();

        return new JsonResponse(['message' => 'Główny trener został ustawiony.']);
    }
}
