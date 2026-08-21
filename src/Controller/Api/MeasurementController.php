<?php

namespace App\Controller\Api;

use App\Entity\BodyParts;
use App\Entity\Meseurments;
use App\Entity\User;
use App\Repository\MeseurmentsRepository;
use App\Service\MeasurementService;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/api/measurements')]
#[IsGranted('ROLE_USER')]
class MeasurementController extends AbstractController
{
    #[Route('', name: 'api_measurements_get', methods: ['GET'])]
    public function index(MeseurmentsRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $measurements = $repository->findBy(['User' => $user], ['date' => 'DESC']);
        
        return $this->json($measurements, Response::HTTP_OK, [], ['groups' => ['measurement:read']]);
    }

    #[Route('', name: 'api_measurements_create', methods: ['POST'])]
    public function create(Request $request, MeasurementService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Brak danych JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $measurement = $service->createMeasurement($user, $data);
        return $this->json($measurement, Response::HTTP_CREATED, [], ['groups' => ['measurement:read']]);
    }

    #[Route('/{id}', name: 'api_measurements_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, MeseurmentsRepository $repository, MeasurementService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $measurement = $repository->findUserMeasurement($id, $user);
        if (!$measurement) {
            return new JsonResponse(['error' => 'Nie znaleziono pomiaru lub brak dostępu.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $measurement = $service->updateMeasurement($measurement, $user, $data);
        return $this->json($measurement, Response::HTTP_OK, [], ['groups' => ['measurement:read']]);
    }

    #[Route('/{id}', name: 'api_measurements_delete', methods: ['DELETE'])]
    public function delete(int $id, MeseurmentsRepository $repository, MeasurementService $service): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $measurement = $repository->findUserMeasurement($id, $user);
        if (!$measurement) {
            return new JsonResponse(['error' => 'Nie znaleziono pomiaru lub brak dostępu.'], Response::HTTP_NOT_FOUND);
        }

        $service->deleteMeasurement($measurement, $user);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
