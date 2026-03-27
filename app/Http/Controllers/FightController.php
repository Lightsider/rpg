<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Application\Battle\RoundExpirationHandler;
use App\Application\Battle\SubmitBattleActions;
use App\Application\Battle\BattleLobbyService;
use App\Application\Battle\BattleViewFactory;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\Battle;
use App\Domain\Character\Character;
use App\Domain\DomainException;
use App\Http\Requests\SubmitActionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FightController extends Controller
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleLogRepositoryInterface $battleLogRepository,
        private readonly RoundExpirationHandler $roundExpirationHandler,
        private readonly SubmitBattleActions $submitBattleActions,
        private readonly BattleLobbyService $battleLobbyService,
        private readonly BattleViewFactory $battleViewFactory
    ) {
    }

    public function index(): JsonResponse
    {
        $this->roundExpirationHandler->handleExpiredRounds();
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $fights = $this->battleRepository->findJoinableByLocation($character->getLocationId());

        $fightsPayload = array_map(function ($fight) {
            $timeout = $fight->getStartTimeoutSeconds();
            $expiresAt = $timeout !== null
                ? $fight->getRoundStartedAt()->modify("+{$timeout} seconds")
                : null;
            $timerRemaining = $expiresAt ? max(0, $expiresAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) : null;

            return array_merge($fight->jsonSerialize(), [
                'timer_remaining' => $timerRemaining,
            ]);
        }, $fights);

        return response()->json($fightsPayload);
    }

    public function create(Request $request): JsonResponse
    {
        $this->roundExpirationHandler->handleExpiredRounds();
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $data = $request->validate([
            'max_participants' => ['nullable', 'integer', 'min:2'],
            'start_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        try {
            $fightId = $this->battleLobbyService->createBattle(
                $character,
                $data['max_participants'] ?? null,
                $data['start_timeout_seconds'] ?? null
            );
            return response()->json(['fight_id' => $fightId], 201);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function join(int $id): JsonResponse
    {
        $this->roundExpirationHandler->handleExpiredRounds();
        $battle = $this->battleRepository->findById($id);

        if (!$battle) {
            return response()->json(['error' => 'Fight not found.'], 404);
        }

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        try {
            $result = $this->battleLobbyService->joinBattle($battle, $character);
            return response()->json($result['battle']);
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
            $this->battleLobbyService->cancelBattle($id, $character);
            return response()->json(['success' => true]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cancel fight.'], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $this->roundExpirationHandler->handleExpiredRounds();
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
        $view = $this->battleViewFactory->build($battle);
        $view['timer_remaining'] = $timerRemaining;

        return response()->json($view);
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
        $this->roundExpirationHandler->handleExpiredRounds();

        $validated = $request->validated();

        try {
            $user = Auth::user();
            $result = $this->submitBattleActions->execute($id, $user->id, $validated['actions']);
            $battle = $result['battle'];

            return response()->json([
                'success' => true,
                'fight_id' => $battle->getId(),
                'status' => $battle->getState()->value,
                'round' => $battle->getRoundNumber(),
                'timer_remaining' => $result['timer_remaining'],
                'actions_submitted' => $result['actions_submitted']
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




