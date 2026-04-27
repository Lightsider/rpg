<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface UserRegistrationNotifierInterface
{
    public function notifyRegistered(int $userId): void;
}

