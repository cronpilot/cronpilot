<?php

namespace App\Enums;

enum ServerType:string
{
    case LOCAL = 'local';
    case REMOTE_SSH = 'remote-ssh';

    public function label(): string
    {
        return match($this) {
            self::LOCAL => 'Local',
            self::REMOTE_SSH => 'Remote SSH',
        };
    }
}
