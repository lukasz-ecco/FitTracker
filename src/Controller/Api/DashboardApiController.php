<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\WeightLogRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class DashboardApiController extends AbstractController
{
    #[Route('/dashboard/summary', name: 'api_dashboard_summary', methods: ['GET'])]
    public function summary(DashboardService $dashboardService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $summary = $dashboardService->getDashboardSummary($user);

        return $this->json($summary, Response::HTTP_OK, [], [
            'groups' => ['plan:read', 'workout:read:full', 'exercise:read', 'template:read', 'dashboard:read']
        ]);
    }

    #[Route('/weight-logs', name: 'api_weight_logs_index', methods: ['GET'])]
    public function weightLogs(WeightLogRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $logs = $repository->findUserWeightLogs($user, 60);

        return $this->json($logs, Response::HTTP_OK, [], [
            'groups' => ['weight_log:read']
        ]);
    }

    #[Route('/weight-logs', name: 'api_weight_logs_create', methods: ['POST'])]
    public function createWeightLog(Request $request, DashboardService $dashboardService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['weight'])) {
            return new JsonResponse(['error' => 'Pole "weight" jest wymagane.'], Response::HTTP_BAD_REQUEST);
        }

        $weight = (float) $data['weight'];
        if ($weight <= 0 || $weight > 500) {
            return new JsonResponse(['error' => 'Nieprawidłowa wartość wagi (musi być między 1 a 500 kg).'], Response::HTTP_BAD_REQUEST);
        }

        $date = null;
        if (!empty($data['date'])) {
            try {
                $date = new \DateTime($data['date']);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Nieprawidłowy format daty (oczekiwany YYYY-MM-DD).'], Response::HTTP_BAD_REQUEST);
            }
        }

        $notes = isset($data['notes']) ? (string) $data['notes'] : null;

        $log = $dashboardService->recordWeight($user, $weight, $date, $notes);

        return $this->json($log, Response::HTTP_CREATED, [], [
            'groups' => ['weight_log:read']
        ]);
    }
}
