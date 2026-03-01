<?php

declare(strict_types=1);

namespace App\Domain\Battle\Repositories;

use App\Domain\Battle\Battle;

interface BattleRepositoryInterface
{
    public function findById(int $id): ?Battle;
    public function save(Battle $battle): void;

    /**
     * @return array<int, Battle>
     */
    public function findActive(): array;
}
