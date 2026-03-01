<?php

namespace App\Application\User\Actions;

use App\Domain\User\Entities\UserEntity;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\UserRegistrationDTO;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class RegisterUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(UserRegistrationDTO $dto): UserEntity
    {
        $userEntity = UserEntity::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ]);

        $savedUser = $this->userRepository->create($userEntity);

        if (method_exists($this->userRepository, 'findByIdModel')) {
            $userModel = $this->userRepository->findByIdModel($savedUser->id);
            if ($userModel) {
                event(new Registered($userModel));
            }
        }

        return $savedUser;
    }
}
