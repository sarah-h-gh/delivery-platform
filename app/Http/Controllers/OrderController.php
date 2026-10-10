<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActiveOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(ActiveOrdersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $orders = Order::select(['id', 'status', 'user_id', 'store_id', 'driver_id', 'created_at'])
            ->active()
            ->when(isset($filters['driver_id']), fn ($query) => $query->forDriver($filters['driver_id']))
            ->whereHas('store')
            ->with(['user:id,name', 'store:id,name', 'driver:id,name'])
            ->withCount('items')
            ->oldestFirst()
            ->paginate(20)
            ->withQueryString();

        return OrderResource::collection($orders);
    }
}
