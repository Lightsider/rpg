<?php

declare(strict_types=1);

namespace App\Application\Location;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Location\Repositories\LocationRepositoryInterface;

class ShowLocation
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $locationId): array
    {
        $location = $this->locationRepository->findById($locationId);
        if (!$location) {
            throw new DomainException('Location not found.');
        }

        $activeFights = $this->battleRepository->findActiveByLocation($locationId);

        return [
            'id' => $location->getId(),
            'name' => $location->getName(),
            'description' => $location->getDescription(),
            'active_fights' => $activeFights,
        ];
    }
}
