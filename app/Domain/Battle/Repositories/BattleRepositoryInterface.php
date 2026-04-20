<?php

declare(strict_types=1);

namespace App\Domain\Battle\Repositories;

use App\Domain\Battle\Battle;

interface BattleRepositoryInterface
{
    public function findById(int $id): ?Battle;
    public function save(Battle $battle): int;

    /**
     * @return array<int, Battle>
     */
    public function findActive(): array;

    /**
     * @return array<int, Battle>
     */
    public function findActiveByLocation(int $locationId): array;
    /**
     * @return array<int, Battle>
     */
    public function findRecent(int $limit = 15): array;

    /**
     * @return array<int, Battle>
     */
    public function findWaitingByLocation(int $locationId): array;

    /**
     * Returns all WAITING or ACTIVE battles in the location.
     * @return array<int, Battle>
     */
    public function findJoinableByLocation(int $locationId): array;

    public function isCharacterInBattle(int $characterId): bool;

    public function findActiveBattleForCharacter(int $characterId): ?Battle;

    /**
     * @return array<int, Battle>
     */
    public function findWaiting(): array;

    public function lockForUpdate(int $battleId): bool;

    public function delete(int $battleId): void;

    public function removeParticipant(int $battleId, int $characterId): void;

    public function hasParticipant(int $battleId, int $characterId): bool;
}
