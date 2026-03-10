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
}
