<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Contracts\PasswordHasherInterface;
use Illuminate\Support\Facades\Hash;

class LaravelPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainText): string
    {
        return Hash::make($plainText);
    }
}

