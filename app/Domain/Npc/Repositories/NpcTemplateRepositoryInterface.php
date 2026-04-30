<?php

declare(strict_types=1);

namespace App\Domain\Npc\Repositories;

use App\Domain\Npc\NpcTemplate;

interface NpcTemplateRepositoryInterface
{
    public function findById(int $id): ?NpcTemplate;

    /**
     * @return NpcTemplate[]
     */
    public function findAll(): array;
}
