<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Services\BackpackService;

class EquipItem
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackService $backpackService
    ) {
    }

    /**
     * @return array{character:mixed, equipment:array, backpack:array}
     */
    public function execute(int $userId, int $itemId, string $slot): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $characterModel = CharacterModel::where('user_id', $userId)->first();
        if (!$characterModel) {
            throw new DomainException('Character not found.');
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            throw new DomainException('Cannot edit loadout during an active fight.');
        }

        $this->backpackService->equipItem($characterModel, $itemId, $slot);

        $characterModel->refresh();
        $updatedCharacter = $this->characterRepository->findByUserId($userId);

        return [
            'character' => $updatedCharacter,
            'equipment' => $this->backpackService->getEquipmentPayload($characterModel),
            'backpack' => $this->backpackService->getBackpackPayload($characterModel),
        ];
    }
}
