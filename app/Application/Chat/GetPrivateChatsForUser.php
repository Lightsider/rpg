<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetPrivateChatsForUser
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly GetPrivateChats $getPrivateChats
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $this->getPrivateChats->execute($character->getId());
    }
}
