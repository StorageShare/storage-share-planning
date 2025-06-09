<?php

namespace App\Enums;

enum TaskPriority: string
{
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';

    public function label(): string
    {
        return match ($this) {
            self::HIGH => 'Hoog',
            self::NORMAL => 'Normaal',
            self::LOW => 'Laag',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
} 