<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ApiExceptionSubscriberTest extends TestCase
{
    private ApiExceptionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ApiExceptionSubscriber();
    }

    public function testOnKernelExceptionIgnoresNonApiRequests(): void
    {
        $request = Request::create('/web/dashboard');
        $exception = new \Exception('Some error');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $this->subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelExceptionHandlesValidationException(): void
    {
        $request = Request::create('/api/measurements');
        $violations = new \Symfony\Component\Validator\ConstraintViolationList();
        $violation = new \Symfony\Component\Validator\ConstraintViolation(
            'Waga jest wymagana', 
            null, 
            [], 
            '', 
            'weight', 
            null
        );
        $violations->add($violation);
        
        $exception = new ValidationException($violations);
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['errors' => ['weight' => 'Waga jest wymagana']]),
            $response->getContent()
        );
    }

    public function testOnKernelExceptionHandlesNotFoundHttpException(): void
    {
        $request = Request::create('/api/measurements/123');
        $exception = new NotFoundHttpException('Nie znaleziono pomiaru');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Nie znaleziono pomiaru']),
            $response->getContent()
        );
    }

    public function testOnKernelExceptionHandlesAccessDeniedException(): void
    {
        $request = Request::create('/api/measurements/123');
        $exception = new AccessDeniedException('Brak dostępu');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['error' => 'Brak dostępu']),
            $response->getContent()
        );
    }
}
