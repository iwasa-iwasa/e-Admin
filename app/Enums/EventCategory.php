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

    public function busyWeight(): int
    {
        return match($this) {
            self::VISITOR => 4,        // 来客（顧客）: 最重要
            self::BUSINESS_TRIP => 3,  // 出張: 重要
            self::WORK => 3,           // 業務: 重要
            self::MEETING => 2,        // 会議: 中程度
            self::OTHER => 1,          // その他: 中程度
            self::VACATION => 0,       // 休暇: 重みなし
        };
    }
}
