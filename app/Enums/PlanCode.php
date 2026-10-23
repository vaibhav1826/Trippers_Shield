<?php

namespace App\Enums;

enum PlanCode: string
{
    case BASIC = 'BASIC';
    case PLUS = 'PLUS';
    case PREMIUM = 'PREMIUM';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function baseDailyRate(): string
    {
        return match ($this) {
            self::BASIC => '12.00',
            self::PLUS => '20.00',
            self::PREMIUM => '30.00',
        };
    }
}
