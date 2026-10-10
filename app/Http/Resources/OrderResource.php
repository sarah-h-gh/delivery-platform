<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'customer_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'store_name' => $this->whenLoaded('store', fn () => $this->store->name),
            'driver_name' => $this->whenLoaded('driver', fn () => $this->driver->name),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
