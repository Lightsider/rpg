<?php

namespace App\Http\Controllers;

use App\Application\Game\GetGameState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function __construct(
        private readonly GetGameState $getGameState
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $payload = $this->getGameState->execute($user->id, $user->name);
        return response()->json($payload);
    }
}
