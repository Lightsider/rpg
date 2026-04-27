<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface PasswordHasherInterface
{
    public function hash(string $plainText): string;
}

