<?php

namespace App\Services;

use App\Contracts\ActiveOrders;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentActiveOrders implements ActiveOrders
{
    public function paginate(int|string|null $driverId = null): LengthAwarePaginator
    {
        return Order::select(['id', 'status', 'user_id', 'store_id', 'driver_id', 'created_at'])
            ->active()
            ->when($driverId !== null, fn ($query) => $query->forDriver($driverId))
            ->whereHas('store')
            ->with(['user:id,name', 'store:id,name', 'driver:id,name'])
            ->withCount('items')
            ->oldestFirst()
            ->paginate(20)
            ->withQueryString();
    }
}
