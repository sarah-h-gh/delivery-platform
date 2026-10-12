<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'status' => OrderStatus::Pending,
            'driver_id' => null,
            'delivery_fee' => '2.50',
            'total_price' => '2.50',
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state([
            'status' => $status,
            'driver_id' => in_array($status, [OrderStatus::Accepted, OrderStatus::PickedUp, OrderStatus::Delivered], true)
                ? Driver::factory() : null,
        ]);
    }

    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
