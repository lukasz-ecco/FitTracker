<?php

namespace App\Controller\Api;

use App\Entity\BodyParts;
use App\Entity\Meseurments;
use App\Entity\User;
use App\Repository\MeseurmentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    public function create(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['size']) || !isset($data['bodyPartId'])) {
            return new JsonResponse(['error' => 'Brak wymaganych danych (size, bodyPartId).'], Response::HTTP_BAD_REQUEST);
        }

        $bodyPart = $em->getRepository(BodyParts::class)->find($data['bodyPartId']);
        if (!$bodyPart) {
            return new JsonResponse(['error' => 'Nie znaleziono części ciała.'], Response::HTTP_NOT_FOUND);
        }

        $measurement = new Meseurments();
        $measurement->setUser($user);
        $measurement->setBodyPart($bodyPart);
        $measurement->setSize((float) $data['size']);
        
        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $measurement->setDate($date);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Nieprawidłowy format daty.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $errors = $validator->validate($measurement);
        if (count($errors) > 0) {
            return $this->jsonErrors($errors);
        }

        $em->persist($measurement);
        $em->flush();

        return $this->json($measurement, Response::HTTP_CREATED, [], ['groups' => ['measurement:read']]);
    }

    #[Route('/{id}', name: 'api_measurements_update', methods: ['PUT', 'PATCH'])]
    public function update(Meseurments $measurement, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        if ($measurement->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Nieprawidłowe dane JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['size'])) {
            $measurement->setSize((float) $data['size']);
        }
        
        if (isset($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
                $measurement->setDate($date);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Nieprawidłowy format daty.'], Response::HTTP_BAD_REQUEST);
            }
        }
        
        if (isset($data['bodyPartId'])) {
            $bodyPart = $em->getRepository(BodyParts::class)->find($data['bodyPartId']);
            if ($bodyPart) {
                $measurement->setBodyPart($bodyPart);
            }
        }

        $errors = $validator->validate($measurement);
        if (count($errors) > 0) {
            return $this->jsonErrors($errors);
        }

        $em->flush();

        return $this->json($measurement, Response::HTTP_OK, [], ['groups' => ['measurement:read']]);
    }

    #[Route('/{id}', name: 'api_measurements_delete', methods: ['DELETE'])]
    public function delete(Meseurments $measurement, EntityManagerInterface $em): JsonResponse
    {
        if ($measurement->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Brak dostępu.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($measurement);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    
    private function jsonErrors($errors): JsonResponse
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[$error->getPropertyPath()] = $error->getMessage();
        }
        return new JsonResponse(['errors' => $messages], Response::HTTP_BAD_REQUEST);
    }
}
