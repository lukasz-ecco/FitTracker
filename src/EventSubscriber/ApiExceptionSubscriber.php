<?php

namespace App\EventSubscriber;

use App\Exception\ValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        
        // Zastosuj tylko dla zapytań do API
        if (strpos($request->getPathInfo(), '/api/') !== 0) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ValidationException) {
            $response = new JsonResponse([
                'errors' => $exception->getErrors()
            ], Response::HTTP_BAD_REQUEST);
        } elseif ($exception instanceof \InvalidArgumentException) {
            $response = new JsonResponse([
                'error' => $exception->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        } elseif ($exception instanceof AccessDeniedException) {
            $response = new JsonResponse([
                'error' => $exception->getMessage() ?: 'Brak dostępu.'
            ], Response::HTTP_FORBIDDEN);
        } elseif ($exception instanceof NotFoundHttpException) {
            $response = new JsonResponse([
                'error' => $exception->getMessage() ?: 'Nie znaleziono zasobu.'
            ], Response::HTTP_NOT_FOUND);
        } elseif ($exception instanceof ConflictHttpException) {
            $response = new JsonResponse([
                'error' => $exception->getMessage() ?: 'Wystąpił konflikt.'
            ], Response::HTTP_CONFLICT);
        } else {
            return;
        }

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
