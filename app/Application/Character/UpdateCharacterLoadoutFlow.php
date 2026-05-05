<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Services\BackpackService;
use App\Services\CharacterStatService;
use App\Services\CharacterStatValidator;

class UpdateCharacterLoadoutFlow
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterStatValidator $statValidator,
        private readonly CharacterStatService $statService,
        private readonly BackpackService $backpackService
    ) {
    }

    /**
     * @param array{strength:int,dexterity:int,constitution:int,wit:int} $stats
     * @return array{character_id:int,unequipped:array}
     */
    public function execute(int $userId, array $stats): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            throw new DomainException('Cannot edit loadout during an active fight.');
        }

        $normalized = [
            'str' => (int) $stats['strength'],
            'con' => (int) $stats['constitution'],
            'dex' => (int) $stats['dexterity'],
            'wit' => (int) $stats['wit'],
        ];

        $this->statValidator->validateStats($normalized);

        $computedHp = $this->statService->calculateHp($normalized['con']);

        $this->characterRepository->updateBaseStats(
            $character->getId(),
            (int) $stats['strength'],
            (int) $stats['dexterity'],
            (int) $stats['constitution'],
            (int) $stats['wit'],
            $computedHp,
            $computedHp
        );

        $unequipped = $this->backpackService->validateEquippedItemsByCharacterId($character->getId());

        return [
            'character_id' => $character->getId(),
            'unequipped' => $unequipped,
        ];
    }
}

