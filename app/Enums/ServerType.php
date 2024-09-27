<?php

namespace App\Enums;

enum ServerType:string
{
    case LOCAL = 'local';
    case QA = 'qa';
    case DEMO = 'demo';
    case PRODUCTION = 'production';
}
