<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Battle\CancelBattle;
use App\Application\Battle\CreateBattle;
use App\Application\Battle\GetBattleLog;
use App\Application\Battle\JoinBattle;
use App\Application\Battle\ListBattles;
use App\Application\Battle\ShowBattle;
use App\Application\Battle\SubmitBattleActions;
use App\Application\Battle\ListRecentBattles;
use App\Domain\DomainException;
use App\Http\Requests\SubmitActionsRequest;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FightController extends Controller
{
    public function __construct(
        private readonly ListBattles $listBattles,
        private readonly CreateBattle $createBattle,
        private readonly JoinBattle $joinBattle,
        private readonly CancelBattle $cancelBattle,
        private readonly ShowBattle $showBattle,
        private readonly GetBattleLog $getBattleLog,
        private readonly SubmitBattleActions $submitBattleActions,
        private readonly ListRecentBattles $listRecentBattles
    ) {
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();
        try {
            $payload = $this->listBattles->execute($user->id);
            return response()->json($payload);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'max_participants' => ['nullable', 'integer', 'min:2'],
            'start_timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        try {
            $fightId = $this->createBattle->execute(
                $user->id,
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
        $user = Auth::user();

        try {
            $result = $this->joinBattle->execute($user->id, $id);
            return response()->json($result['battle']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $this->cancelBattle->execute($user->id, $id);
            return response()->json(['success' => true]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cancel fight.'], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        try {
            $view = $this->showBattle->execute($user->id, $id);
            return response()->json($view);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getMessage() === 'Fight not found.' ? 404 : 403);
        }
    }

    public function log(int $id): JsonResponse
    {
        $user = Auth::user();
        try {
            $logs = $this->getBattleLog->execute($user->id, $id);
            return response()->json($logs);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getMessage() === 'Fight not found.' ? 404 : 403);
        }
    }

    public function submitActions(int $id, SubmitActionsRequest $request): JsonResponse
    {
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

    public function history(): InertiaResponse
    {
        return Inertia::render('Battles/History');
    }

    public function recent(): JsonResponse
    {
        try {
            $battles = $this->listRecentBattles->execute(20);
            return response()->json($battles);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function showHistory(int $id): InertiaResponse
    {
        return Inertia::render('Battles/Show', [
            'fightId' => $id
        ]);
    }
}




