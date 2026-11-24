<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection($this->orderService->list($request->user()));
    }

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->place($request->user(), $request->validated('items'));

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('items.product'));
    }
}
