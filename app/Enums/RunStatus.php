<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RunStatus: string implements HasColor, HasIcon, HasLabel
{
    case SUCCESSFUL = 'Successful';
    case RUNNING = 'Running';
    case FAILED = 'Failed';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'success',
            self::RUNNING => 'gray',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'tabler-check',
            self::RUNNING => 'tabler-loader',
            self::FAILED => 'tabler-x',
        };
    }
}
