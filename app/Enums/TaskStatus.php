<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel
{
    case PREFLIGHT = 'Pre-flight';
    case ACTIVE = 'Active';
    case DISABLED = 'Disabled';

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
