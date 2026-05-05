<?php

declare(strict_types=1);

namespace App\Application\Game;

use App\Application\Battle\BattleSummaryPresenter;
use App\Application\Character\CharacterPresenter;
use App\Application\Contracts\ClockInterface;
use App\Application\Location\LocationPresenter;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Services\BackpackService;
use App\Services\CharacterStatService;
use App\Services\CharacterStatValidator;

class GetGameState
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackService $backpackService,
        private readonly CharacterStatValidator $statValidator,
        private readonly CharacterStatService $statService,
        private readonly ClockInterface $clock,
        private readonly BattleSummaryPresenter $battleSummaryPresenter,
        private readonly CharacterPresenter $characterPresenter,
        private readonly LocationPresenter $locationPresenter
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, string $userName): array
    {
        $character = $this->characterRepository->findByUserId($userId);

        if (!$character) {
            $defaultStats = [
                'str' => 4,
                'con' => 4,
                'dex' => 4,
                'wit' => 4,
            ];

            $this->statValidator->validateStats($defaultStats);

            $computedHp = $this->statService->calculateHp($defaultStats['con']);

            $newCharacter = new Character(
                id: 0,
                userId: $userId,
                name: $userName,
                strength: $defaultStats['str'],
                agility: $defaultStats['dex'],
                constitution: $defaultStats['con'],
                wit: $defaultStats['wit'],
                maxHp: $computedHp,
                currentHp: $computedHp,
                equipment: new Equipment(),
                locationId: 1,
                level: 1,
                experience: 0
            );
            $character = $this->characterRepository->create($newCharacter);
        }

        $this->backpackService->ensureSeededByUserId($userId);
        $character = $this->characterRepository->findByUserId($userId) ?? $character;

        $location = $this->locationRepository->findById($character->getLocationId());

        $currentFight = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($currentFight && $currentFight->getState() === BattleState::WAITING) {
            $timeout = $currentFight->getStartTimeoutSeconds();
            $expiresAt = $timeout !== null
                ? $currentFight->getRoundStartedAt()->modify("+{$timeout} seconds")
                : null;
            $timerRemaining = $expiresAt ? max(0, $expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) : null;

            $currentFight = array_merge($this->battleSummaryPresenter->present($currentFight), [
                'timer_remaining' => $timerRemaining,
            ]);
        } elseif ($currentFight) {
            $currentFight = $this->battleSummaryPresenter->present($currentFight);
        }

        $availableFights = $this->battleRepository->findActiveByLocation($character->getLocationId());

        return [
            'character' => $this->characterPresenter->present($character),
            'location' => $location ? $this->locationPresenter->present($location) : null,
            'availableFights' => $availableFights,
            'currentFight' => $currentFight,
            'canCreateFight' => $currentFight === null,
            'canLeaveLocation' => $currentFight === null,
        ];
    }
}
