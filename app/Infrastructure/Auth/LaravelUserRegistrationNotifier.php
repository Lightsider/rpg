<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Contracts\EventDispatcherInterface;
use App\Application\Contracts\UserRegistrationNotifierInterface;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Auth\Events\Registered;

class LaravelUserRegistrationNotifier implements UserRegistrationNotifierInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function notifyRegistered(int $userId): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        $this->eventDispatcher->dispatch(new Registered($user));
    }
}
