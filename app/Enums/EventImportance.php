<?php

namespace App\Enums;

enum EventImportance: string
{
    case HIGH = '重要';
    case MEDIUM = '中';
    case LOW = '低';

    public function label(): string
    {
        return match($this) {
            self::HIGH => '重要',
            self::MEDIUM => '中',
            self::LOW => '低',
        };
    }

    public function busyWeight(): int
    {
        return match($this) {
            self::HIGH => 3,
            self::MEDIUM => 2,
            self::LOW => 1,
        };
    }
}
