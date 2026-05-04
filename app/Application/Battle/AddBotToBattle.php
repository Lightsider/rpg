<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
use App\Events\Battle\BattleJoined;
use App\Application\Contracts\EventDispatcherInterface;
use App\Application\Contracts\TransactionInterface;


class AddBotToBattle
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly NpcTemplateRepositoryInterface $npcTemplateRepository,
        private readonly TransactionInterface $transaction,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NpcFactory $npcFactory
    ) {
    }

    /**
     * @throws DomainException
     */
    public function execute(int $battleId, int $npcTemplateId): void
    {
        $pendingEvents = [];

        $this->transaction->run(function () use ($battleId, $npcTemplateId, &$pendingEvents) {
            if (!$this->battleRepository->lockForUpdate($battleId)) {
                throw new DomainException('Fight not found.');
            }

            $battle = $this->battleRepository->findById($battleId);
            if (!$battle || $battle->getState() !== BattleState::WAITING) {
                throw new DomainException('Fight is no longer in lobby phase.');
            }

            $template = $this->npcTemplateRepository->findById($npcTemplateId);
            if (!$template) {
                throw new DomainException('NPC Template not found.');
            }

            $max = $battle->getMaxParticipants();
            if ($max !== null && count($battle->getParticipants()) >= $max) {
                throw new DomainException('Fight is full.');
            }

            $combatantId = $this->battleRepository->generateNpcCombatantId($battleId, $template->id, 'team_2');
            $npc = $this->npcFactory->createFromTemplate($template, $combatantId);

            $battle->addParticipant($npc);
            $battle->assignTeam($combatantId, 'team_2'); // Team 2 for bots

            // Since EloquentBattleRepository mapToDomain loads directly from DB,
            // we should save the battle state (though participants are synced differently)
            $this->battleRepository->save($battle);

            // We don't automatically start the battle here, let tryStartWaitingBattle or manual start handle it.
            // Or if we want to check if it's full now:
            $count = count($battle->getParticipants());
            if ($max !== null && $count >= $max) {
                $battle->startFromLobby();
                $this->battleRepository->save($battle);
                // Start events would be dispatched here if we replicated tryStartWaitingBattle logic
            }
        });

        foreach ($pendingEvents as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
