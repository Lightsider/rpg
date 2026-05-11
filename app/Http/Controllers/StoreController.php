<?php

namespace App\Http\Controllers;

use App\Application\Store\GetStoreItems;
use App\Application\Store\PurchaseStoreItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly GetStoreItems $getStoreItems,
        private readonly PurchaseStoreItem $purchaseStoreItem
    ) {
    }

    public function open(int $id, Request $request): JsonResponse
    {
        $items = $this->getStoreItems->execute($id);

        return response()->json([
            'status' => 'success',
            'items' => $items,
            'message' => 'Store opened'
        ]);
    }

    public function buy(Request $request): JsonResponse
    {
        $request->validate([
            'store_item_id' => 'required|integer',
        ]);
        $userId = $request->user()->id;
        $this->purchaseStoreItem->execute($userId, (int) $request->input('store_item_id'));

        return response()->json([
            'status' => 'success',
            'message' => 'Item purchased successfully'
        ]);
    }
}





