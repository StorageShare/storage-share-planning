<?php

namespace App\Enums;

enum VehicleType: string
{
    case CAR = 'car';
    case BUS = 'bus';

    public function label(): string
    {
        return match ($this) {
            self::CAR => 'Auto',
            self::BUS => 'Bus',
        };
    }

    /**
     * Key/value options suitable for selects: [value => label]
     */
    public static function options(): array
    {
        $opts = [];
        foreach (self::cases() as $case) {
            $opts[$case->value] = $case->label();
        }
        return $opts;
    }

    /**
     * Convenience method returning backing values only.
     */
    public static function values(): array
    {
        return array_map(static fn(self $c) => $c->value, self::cases());
    }
}
