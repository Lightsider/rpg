<?php

declare(strict_types=1);

namespace App\Application\Location;

use App\Domain\Location\Location;

class LocationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Location $location): array
    {
        return [
            'id' => $location->getId(),
            'name' => $location->getName(),
            'description' => $location->getDescription(),
            'max_players' => $location->getMaxPlayers(),
            'start_timeout_seconds' => $location->getStartTimeoutSeconds(),
            'connected_locations' => $location->getConnectedLocationIds(),
            'npc_list' => $location->getNpcIds(),
            'available_fights' => [],
        ];
    }
}

