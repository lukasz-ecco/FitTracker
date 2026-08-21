<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\TrainerTraineeConnection;
use App\Repository\TrainerTraineeConnectionRepository;
use App\Service\TrainerInvitationService;
use App\Service\TrainerTraineeConnectionService;
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
    public function index(Request $request, TrainerTraineeConnectionRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $as = $request->query->get('as');
        $isTrainerOrAdmin = array_intersect(['ROLE_TRAINER', 'ROLE_ADMIN'], $user->getRoles()) !== [];
        
        // Domyślnie pobieramy powiązania, w których użytkownik jest podopiecznym.
        // Wyjątek: użytkownik jest trenerem/adminem i celowo nie zażądał widoku 'trainee'
        $searchField = ($isTrainerOrAdmin && $as !== 'trainee') ? 'trainer' : 'trainee';
        
        $connections = $repository->findBy([$searchField => $user]);

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

        $invitationService->invite($user, $data['email']);
        return new JsonResponse(['message' => 'Zaproszenie wysłane pomyślnie.'], Response::HTTP_CREATED);
    }

    #[Route('/{id}/accept', name: 'api_connections_accept', methods: ['POST'])]
    public function accept(int $id, TrainerTraineeConnectionRepository $repository, TrainerTraineeConnectionService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $connection = $repository->findConnection($id, $user);
        if (!$connection) {
            return new JsonResponse(['error' => 'Brak dostępu lub połączenie nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->acceptConnection($connection, $user);
        return new JsonResponse(['message' => 'Zaproszenie zaakceptowane.']);
    }

    #[Route('/{id}/reject', name: 'api_connections_reject', methods: ['POST'])]
    public function reject(int $id, TrainerTraineeConnectionRepository $repository, TrainerTraineeConnectionService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $connection = $repository->findConnection($id, $user);
        if (!$connection) {
            return new JsonResponse(['error' => 'Brak dostępu lub połączenie nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->rejectConnection($connection, $user);
        return new JsonResponse(['message' => 'Zaproszenie odrzucone.']);
    }

    #[Route('/{id}/set-main', name: 'api_connections_set_main', methods: ['POST'])]
    public function setMain(int $id, TrainerTraineeConnectionRepository $repository, TrainerTraineeConnectionService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $connection = $repository->findConnection($id, $user);
        if (!$connection) {
            return new JsonResponse(['error' => 'Brak dostępu lub połączenie nie istnieje.'], Response::HTTP_NOT_FOUND);
        }

        $service->setMainConnection($connection, $user);
        return new JsonResponse(['message' => 'Główny trener został ustawiony.']);
    }
}
