<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Application\Battle\QueueAttackAction;
use App\Application\Battle\QueueDefenseAction;
use App\Application\Battle\QueueMoveAction;
use App\Application\Battle\CommitRoundAction;
use App\Application\Battle\LeaveWaitingBattleAction;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Infrastructure\Eloquent\Models\FightMapModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use App\Services\MapGenerator;
use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Http\Requests\SubmitActionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FightController extends Controller
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly QueueAttackAction $queueAttackAction,
        private readonly QueueDefenseAction $queueDefenseAction,
        private readonly QueueMoveAction $queueMoveAction,
        private readonly CommitRoundAction $commitRoundAction,
        private readonly LeaveWaitingBattleAction $leaveWaitingBattleAction,
        private readonly MapGenerator $mapGenerator
    ) {
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $fights = $this->battleRepository->findJoinableByLocation($character->getLocationId());

        return response()->json($fights);
    }

    public function create(): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            return response()->json(['error' => 'Character is already in another fight.'], 400);
        }

        $battle = new Battle(
            id: 0,
            locationId: $character->getLocationId(),
            participants: [$character->getId() => $character],
            map: Map::default(),
            state: BattleState::WAITING
        );

        $fightId = $this->battleRepository->save($battle);

        $battle = $this->battleRepository->findById($fightId);
        if ($battle) {
            $this->mapGenerator->generateForFight($battle);
        }

        return response()->json(['fight_id' => $fightId], 201);
    }

    public function join(int $id): JsonResponse
    {
        $battle = $this->battleRepository->findById($id);

        if (!$battle) {
            return response()->json(['error' => 'Fight not found.'], 404);
        }

        if ($battle->getState() !== BattleState::WAITING) {
            return response()->json(['error' => 'Fight is no longer joinable.'], 400);
        }

        if (count($battle->getParticipants()) >= 2) {
            return response()->json(['error' => 'Fight is full.'], 400);
        }

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        if ($this->battleRepository->isCharacterInBattle($character->getId())) {
            return response()->json(['error' => 'Character is already in another fight.'], 400);
        }

        try {
            $battle->addParticipant($character);
            $this->battleRepository->save($battle);

            $battle = $this->battleRepository->findById($battle->getId());
            if ($battle) {
                $this->mapGenerator->generateForFight($battle);
            }

            return response()->json($battle);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        try {
            $this->leaveWaitingBattleAction->execute($id, $character->getId());
            return response()->json(['success' => true]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cancel fight.'], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $battle = $this->battleRepository->findById($id);

        if (!$battle) {
            return response()->json(['error' => 'Fight not found.'], 404);
        }

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character || !$battle->getParticipantById($character->getId())) {
            return response()->json(['error' => 'You are not a participant in this fight.'], 403);
        }

        $timerRemaining = $this->calculateTimerRemaining($battle);

        $mapModel = FightMapModel::where('fight_id', $battle->getId())->first();
        $mapWidth = $mapModel?->width ?? $battle->getMap()->getWidth();
        $mapHeight = $mapModel?->height ?? $battle->getMap()->getHeight();

        $positions = FighterPositionModel::where('fight_id', $battle->getId())
            ->get()
            ->map(fn(FighterPositionModel $pos) => [
                'character_id' => $pos->user_id,
                'x' => $pos->x,
                'y' => $pos->y,
            ])->toArray();

        return response()->json([
            'fight_id' => $battle->getId(),
            'status' => $battle->getState()->value,
            'round' => $battle->getRoundNumber(),
            'participants' => array_map(fn(Character $p) => [
                'character_id' => $p->getId(),
                'name' => $p->getName(),
                'hp' => $p->getCurrentHp(),
                'max_hp' => $p->getMaxHp()
            ], array_values($battle->getParticipants())),
            'map' => [
                'width' => $mapWidth,
                'height' => $mapHeight,
            ],
            'positions' => $positions,
            'timer_remaining' => $timerRemaining,
            'actions_submitted' => $battle->getCommittedCharacterIds()
        ]);
    }

    public function log(int $id): JsonResponse
    {
        $battle = $this->battleRepository->findById($id);

        if (!$battle) {
            return response()->json(['error' => 'Fight not found.'], 404);
        }

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character || !$battle->getParticipantById($character->getId())) {
            return response()->json(['error' => 'You are not a participant in this fight.'], 403);
        }

        $logs = $this->battleLogRepository->findByBattleId($id);

        return response()->json($logs);
    }

    public function submitActions(int $id, SubmitActionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $battle = $this->battleRepository->findById($id);
        if (!$battle) {
            return response()->json(['error' => 'Fight not found.'], 404);
        }

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character || !$battle->getParticipantById($character->getId())) {
            return response()->json(['error' => 'You are not a participant in this fight.'], 403);
        }

        // Custom validation: max 2 attacks
        $attacksCount = collect($validated['actions'])->where('type', 'attack')->count();
        if ($attacksCount > 2) {
            return response()->json(['error' => 'Maximum 2 attacks per round.'], 400);
        }

        try {
            foreach ($validated['actions'] as $actionData) {
                $type = $actionData['type'];
                // Normalize "block" to "defend" if needed, and "body" to "torso"
                $zone = $actionData['zone'] ?? null;
                if ($zone === 'body') {
                    $zone = 'torso';
                }

                if ($type === 'attack') {
                    $this->queueAttackAction->execute($id, $character->getId(), $zone);
                } elseif ($type === 'block') {
                    $this->queueDefenseAction->execute($id, $character->getId(), $zone);
                } elseif ($type === 'move') {
                    $target = $actionData['target'];
                    $blocks = $actionData['blocks'] ?? [];
                    $this->queueMoveAction->execute($id, $character->getId(), (int) $target['x'], (int) $target['y'], $blocks);
                }
                // Move implementation could be added here if needed, 
                // but the prompt focus is on attack/block example.
            }

            // Commit the character's turn
            $this->commitRoundAction->execute($id, $character->getId());

            // Reload battle for updated state
            $battle = $this->battleRepository->findById($id);

            return response()->json([
                'success' => true,
                'fight_id' => $battle->getId(),
                'status' => $battle->getState()->value,
                'round' => $battle->getRoundNumber(),
                'timer_remaining' => $this->calculateTimerRemaining($battle),
                'actions_submitted' => $battle->getCommittedCharacterIds()
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit actions.'], 500);
        }
    }

    private function calculateTimerRemaining(Battle $battle): int
    {
        $now = new \DateTimeImmutable();
        $expiryTime = $battle->getRoundStartedAt()->modify("+{$battle->getRoundDurationSeconds()} seconds");
        return max(0, $expiryTime->getTimestamp() - $now->getTimestamp());
    }
}

