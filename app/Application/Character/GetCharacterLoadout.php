<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Services\BackpackReadService;

class GetCharacterLoadout
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackReadService $backpackReadService
    ) {
    }

    /**
     * @return array{
     *   stats:array{strength:int,dexterity:int,constitution:int,wit:int},
     *   level:int,
     *   experience:int,
     *   equipment:array<string, array<string, mixed>|null>,
     *   backpack:array<int, array<string, mixed>>,
     *   can_edit:bool,
     *   blocked_reason:?string
     * }
     */
    public function execute(int $userId): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        $canEdit = $activeBattle === null;

        return [
            'stats' => [
                'strength' => (int) $character->getStrength(),
                'dexterity' => (int) $character->getAgility(),
                'constitution' => (int) $character->getConstitution(),
                'wit' => (int) $character->getWit(),
            ],
            'level' => (int) $character->getLevel(),
            'experience' => (int) $character->getExperience(),
            'equipment' => $this->backpackReadService->getEquipmentPayloadByCharacterId($character->getId()),
            'backpack' => $this->backpackReadService->getBackpackPayloadByCharacterId($character->getId()),
            'can_edit' => $canEdit,
            'blocked_reason' => $canEdit ? null : 'Cannot edit loadout while you are in a fight.',
        ];
    }
}
