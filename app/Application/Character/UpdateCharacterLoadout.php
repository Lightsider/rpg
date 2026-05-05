<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class UpdateCharacterLoadout
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly UpdateCharacterLoadoutFlow $flow
    ) {
    }

    /**
     * @param array{strength:int,dexterity:int,constitution:int,wit:int} $stats
     * @return array{character:mixed,stats:array{strength:int,dexterity:int,constitution:int,wit:int},unequipped:array}
     */
    public function execute(int $userId, array $stats): array
    {
        $result = $this->flow->execute($userId, $stats);

        $updatedCharacter = $this->characterRepository->findByUserId($userId);
        if (!$updatedCharacter) {
            throw new DomainException('Character not found.');
        }

        return [
            'character' => $updatedCharacter,
            'stats' => [
                'strength' => (int) $updatedCharacter->getStrength(),
                'dexterity' => (int) $updatedCharacter->getAgility(),
                'constitution' => (int) $updatedCharacter->getConstitution(),
                'wit' => (int) $updatedCharacter->getWit(),
            ],
            'unequipped' => $result['unequipped'],
        ];
    }
}

