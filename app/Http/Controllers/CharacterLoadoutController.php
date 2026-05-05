<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Character\GetCharacterLoadout;
use App\Application\Character\UpdateCharacterLoadout;
use App\Http\Requests\UpdateCharacterLoadoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CharacterLoadoutController extends Controller
{
    public function __construct(
        private readonly GetCharacterLoadout $getCharacterLoadout,
        private readonly UpdateCharacterLoadout $updateCharacterLoadout
    ) {
    }

    public function loadout(): JsonResponse
    {
        $user = Auth::user();
        return response()->json($this->getCharacterLoadout->execute($user->id));
    }

    public function update(UpdateCharacterLoadoutRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $payload = $this->updateCharacterLoadout->execute($user->id, [
            'strength' => (int) $data['strength'],
            'dexterity' => (int) $data['dexterity'],
            'constitution' => (int) $data['constitution'],
            'wit' => (int) $data['wit'],
        ]);

        return response()->json($payload);
    }
}
