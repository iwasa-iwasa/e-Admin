<?php

namespace App\Enums;

enum EventColor: string
{
    case BLUE = 'blue';
    case GREEN = 'green';
    case YELLOW = 'yellow';
    case PURPLE = 'purple';
    case PINK = 'pink';
    case GRAY = 'gray';

    public function label(): string
    {
        return match($this) {
            self::BLUE => '青',
            self::GREEN => '緑',
            self::YELLOW => '黄',
            self::PURPLE => '紫',
            self::PINK => 'ピンク',
            self::GRAY => 'グレー',
        };
    }
}
