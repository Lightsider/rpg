<?php

namespace App\Application\User\Actions;

use App\Application\Contracts\PasswordHasherInterface;
use App\Application\Contracts\UserRegistrationNotifierInterface;
use App\Domain\User\Entities\UserEntity;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\UserRegistrationDTO;

class RegisterUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly UserRegistrationNotifierInterface $registrationNotifier
    ) {
    }

    public function execute(UserRegistrationDTO $dto): UserEntity
    {
        $userEntity = UserEntity::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $this->passwordHasher->hash($dto->password),
        ]);

        $savedUser = $this->userRepository->create($userEntity);

        $this->registrationNotifier->notifyRegistered($savedUser->id);

        return $savedUser;
    }
}
