<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'Active';
    case PREFLIGHT = 'Pre-flight';
    case DISABLED = 'Disabled';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::PREFLIGHT => 'info',
            self::DISABLED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'tabler-check',
            self::PREFLIGHT => 'tabler-plane-departure',
            self::DISABLED => 'tabler-slash',
        };
    }
}
