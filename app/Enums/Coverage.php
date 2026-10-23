<?php

namespace App\Enums;

enum Coverage: int
{
    case BASIC_100K = 100000;
    case MEDIUM_300K = 300000;
    case HIGH_500K = 500000;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function multiplier(): string
    {
        return match ($this) {
            self::BASIC_100K => '1.00',
            self::MEDIUM_300K => '1.25',
            self::HIGH_500K => '1.50',
        };
    }

    public static function tryFromInt(int|float|string $value): ?self
    {
        $val = (int) $value;
        return self::tryFrom($val);
    }
}
