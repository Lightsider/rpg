<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\DomainException;
use App\Http\Requests\EquipBackpackItemRequest;
use App\Http\Requests\UnequipBackpackItemRequest;
use App\Application\Character\EquipItem;
use App\Application\Character\UnequipItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BackpackController extends Controller
{
    public function __construct(
        private readonly EquipItem $equipItem,
        private readonly UnequipItem $unequipItem
    ) {
    }

    public function equip(EquipBackpackItemRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        try {
            $payload = $this->equipItem->execute($user->id, (int) $data['item_id'], (string) $data['slot']);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'character' => $payload['character'],
            'equipment' => $payload['equipment'],
            'backpack' => $payload['backpack'],
        ]);
    }

    public function unequip(UnequipBackpackItemRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        try {
            $payload = $this->unequipItem->execute($user->id, (string) $data['slot']);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'character' => $payload['character'],
            'equipment' => $payload['equipment'],
            'backpack' => $payload['backpack'],
        ]);
    }
}
