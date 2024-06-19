<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RunStatus: string implements HasLabel
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
    case PENDING = 'Pending';

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
