<?php
namespace App\Enum;

enum MuscleActivationLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): string
    {
        return match($this){
            self::LOW => 'Niski',
            self::MEDIUM => 'Średni',
            self::HIGH => 'Wysoki'    
        };
    }
}