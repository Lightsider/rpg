<?php

declare(strict_types=1);

namespace App\Domain\Character\Repositories;

use App\Domain\Character\Character;

interface CharacterRepositoryInterface
{
    public function findById(int $id): ?Character;
    public function findByUserId(int $userId): ?Character;
    public function create(Character $character): Character;
    public function updateHp(int $id, int $currentHp): void;
    public function updateCurrency(int $id, int $copper): void;
    public function updateLocation(int $id, int $locationId): void;
    public function updateBaseStats(int $id, int $strength, int $dexterity, int $constitution, int $wit, int $unallocatedStats, int $hp, int $maxHp): void;

    /**
     * @return Character[]
     */
    public function findByLocationId(int $locationId): array;

    /**
     * @param int[] $ids
     * @return Character[]
     */
    public function findManyByIds(array $ids): array;
}
