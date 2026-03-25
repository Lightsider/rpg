<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Battle\PerformAttackAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller to handle battle-related requests.
 */
class BattleController extends Controller
{
    public function __construct(
        private readonly PerformAttackAction $performAttackAction
    ) {
    }

    /**
     * Handles an attack request.
     */
    public function attack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attacker_id' => 'required|integer',
            'defender_id' => 'required|integer',
            'is_blocked' => 'required|boolean',
        ]);

        $result = $this->performAttackAction->execute(
            (int) $validated['attacker_id'],
            (int) $validated['defender_id'],
            (bool) $validated['is_blocked']
        );

        return response()->json([
            'damage' => $result->damage,
            'is_critical' => $result->isCritical,
            'is_dodged' => $result->isDodged,
            'is_miss' => $result->isMiss,
            'damage_type' => $result->damageType->value,
        ]);
    }
}
