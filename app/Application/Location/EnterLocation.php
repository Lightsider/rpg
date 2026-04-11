<?php

declare(strict_types=1);

namespace App\Application\Location;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class EnterLocation
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * @return array{location:mixed}
     */
    public function execute(int $userId, int $locationId): array
    {
        $character = CharacterModel::where('user_id', $userId)->first();
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        if ($this->battleRepository->isCharacterInBattle($userId)) {
            throw new DomainException('You cannot change locations while in a fight.');
        }

        $location = $this->locationRepository->findById($locationId);
        if (!$location) {
            throw new DomainException('Location not found');
        }

        if ((int) $character->location_id !== $location->getId()) {
            $character->update(['location_id' => $location->getId()]);
        }

        return ['location' => $location];
    }
}
