<?php

namespace App\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends \Exception
{
    private array $errors = [];

    public function __construct(ConstraintViolationListInterface $violations)
    {
        parent::__construct('Błąd walidacji danych.');

        foreach ($violations as $violation) {
            $this->errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
