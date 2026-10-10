<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn($query->getModel()->qualifyColumn('status'), [
            OrderStatus::Pending->value,
            OrderStatus::Accepted->value,
            OrderStatus::PickedUp->value,
        ]);
    }

    #[Scope]
    protected function forDriver(Builder $query, int|string $driverId): void
    {
        $query->where($query->getModel()->qualifyColumn('driver_id'), $driverId);
    }

    #[Scope]
    protected function oldestFirst(Builder $query): void
    {
        $query->orderBy($query->getModel()->qualifyColumn('created_at'), 'asc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->orderItems();
    }
}
