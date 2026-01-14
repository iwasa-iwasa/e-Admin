<?php

namespace App\Enums;

enum EventCategory: string
{
    case MEETING = '会議';
    case WORK = '業務';
    case VISITOR = '来客';
    case BUSINESS_TRIP = '出張';
    case VACATION = '休暇';
    case OTHER = 'その他';

    public function label(): string
    {
        return match($this) {
            self::MEETING => '会議',
            self::WORK => '業務',
            self::VISITOR => '来客',
            self::BUSINESS_TRIP => '出張',
            self::VACATION => '休暇',
            self::OTHER => 'その他',
        };
    }

    public function color(): EventColor
    {
        return match($this) {
            self::MEETING => EventColor::BLUE,
            self::WORK => EventColor::GREEN,
            self::VISITOR => EventColor::YELLOW,
            self::BUSINESS_TRIP => EventColor::PURPLE,
            self::VACATION => EventColor::PINK,
            self::OTHER => EventColor::GRAY,
        };
    }
}
