<?php

declare(strict_types=1);

namespace App\Domain\Location\Repositories;

use App\Domain\Location\Location;

interface LocationRepositoryInterface
{
    public function findById(int $id): ?Location;
    public function findAll(): array;
}