<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RunStatus: string implements HasLabel
{
    case RUNNING = 'Running';
    case SUCCESSFUL = 'Successful';
    case FAILED = 'Failed';

    public function getLabel(): ?string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            RunStatus::SUCCESSFUL => 'success',
            RunStatus::FAILED => 'danger',
            RunStatus::RUNNING => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            RunStatus::SUCCESSFUL => 'tabler-check',
            RunStatus::FAILED => 'tabler-x',
            RunStatus::RUNNING => 'tabler-run',
        };
    }
}
