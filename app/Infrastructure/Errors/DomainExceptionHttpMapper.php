<?php

declare(strict_types=1);

namespace App\Infrastructure\Errors;

class DomainExceptionHttpMapper
{
    public static function toStatusCode(string $message): int
    {
        return match ($message) {
            'Character not found.' => 404,
            'Cannot edit loadout during an active fight.' => 400,
            default => 422,
        };
    }
}

