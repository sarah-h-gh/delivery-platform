<?php

namespace App\Http\Controllers;

use App\Contracts\ActiveOrders;
use App\Http\Requests\ActiveOrdersRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly ActiveOrders $activeOrders) {}

    public function index(ActiveOrdersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $orders = $this->activeOrders->paginate($filters['driver_id'] ?? null);

        return OrderResource::collection($orders);
    }
}
