<?php

namespace App\Http\Controllers;

use App\Application\Store\GetStoreItems;
use App\Application\Store\BuyStoreItem;
use App\Infrastructure\WebSockets\Events\StoreStateEvent;
use App\Infrastructure\WebSockets\Events\InventoryUpdateEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    private GetStoreItems $getStoreItems;
    private BuyStoreItem $buyStoreItem;

    public function __construct(GetStoreItems $getStoreItems, BuyStoreItem $buyStoreItem)
    {
        $this->getStoreItems = $getStoreItems;
        $this->buyStoreItem = $buyStoreItem;
    }

    public function open(int $id, Request $request): JsonResponse
    {
        try {
            $items = $this->getStoreItems->execute($id);
            $characterId = $request->user()->character?->id;

            if ($characterId) {
                // Broadcast store state to the user's specific channel
                broadcast(new StoreStateEvent($characterId, $items));
            }

            return response()->json([
                'status' => 'success',
                'items' => $items,
                'message' => 'Store opened and state broadcasted'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function buy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'store_item_id' => 'required|integer',
            ]);

            $characterId = $request->user()->character?->id;
            if (!$characterId) {
                return response()->json(['error' => 'Character not found'], 400);
            }
            
            $this->buyStoreItem->execute($characterId, $request->input('store_item_id'));
            
            // Broadcast inventory update so the client app refreshes
            broadcast(new InventoryUpdateEvent($characterId));

            return response()->json([
                'status' => 'success',
                'message' => 'Item purchased successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}





