<?php

declare(strict_types=1);

namespace App\Domain\Chat;

enum ChatType: string
{
    case LOCATION = 'location';
    case BATTLE = 'battle';
    case PRIVATE = 'private';
}
