<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Day: string implements HasLabel
{
    case SUNDAY = 'SU';
    case MONDAY = 'MO';
    case TUESDAY = 'TU';
    case WEDNESDAY = 'WE';
    case THURSDAY = 'TH';
    case FRIDAY = 'FR';
    case SATURDAY = 'SA';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUNDAY => 'Sunday',
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
        };
    }
}
