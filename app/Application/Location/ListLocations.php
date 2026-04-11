<?php

declare(strict_types=1);

namespace App\Application\Location;

use App\Domain\Location\Repositories\LocationRepositoryInterface;

class ListLocations
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function execute(): array
    {
        return $this->locationRepository->findAll();
    }
}
