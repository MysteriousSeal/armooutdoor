<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Requests\Api\Admin\StoreDraftOrderRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends AdminOrderController
{
    public function createDraft(StoreDraftOrderRequest $request): JsonResponse
    {
        return $this->saveDraft($request, null);
    }

    public function updateDraft(StoreDraftOrderRequest $request, Order $order): JsonResponse
    {
        abort_unless($order->isDraft(), 404);

        return $this->saveDraft($request, $order);
    }

    private function saveDraft(StoreDraftOrderRequest $request, ?Order $order): JsonResponse
    {
        $request->merge(['action' => 'draft']);

        try {
            $savedOrder = $this->saveManualOrder($request, $order);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stock') {
                return response()->json(['message' => 'One of the selected products no longer has enough stock.'], 422);
            }

            throw $exception;
        }

        $savedOrder->load(['items', 'user']);

        return response()->json(['data' => $savedOrder], $order === null ? 201 : 200);
    }
}
