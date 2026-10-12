<?php

namespace App\Services;

use App\Enums\OrderStatus;

class OrderStatusPolicy
{
    /** @var list<string> */
    private readonly array $activeStatuses;

    public function __construct()
    {
        $this->activeStatuses = [
            OrderStatus::Pending->value,
            OrderStatus::Accepted->value,
            OrderStatus::PickedUp->value,
        ];
    }

    /** @return list<string> */
    public function activeValues(): array
    {
        return $this->activeStatuses;
    }

    public function isActive(OrderStatus $status): bool
    {
        return in_array($status->value, $this->activeStatuses, true);
    }
}
