<?php

declare(strict_types=1);

namespace App\Application\Location;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Location\Repositories\LocationRepositoryInterface;

class EnterLocation
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly LocationPresenter $locationPresenter
    ) {
    }

    /**
     * @return array{location:mixed}
     */
    public function execute(int $userId, int $locationId): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            throw new DomainException('You cannot change locations while in a fight.');
        }

        $location = $this->locationRepository->findById($locationId);
        if (!$location) {
            throw new DomainException('Location not found');
        }

        if ($character->getLocationId() !== $location->getId()) {
            $this->characterRepository->updateLocation($character->getId(), $location->getId());
        }

        return ['location' => $this->locationPresenter->present($location)];
    }
}
