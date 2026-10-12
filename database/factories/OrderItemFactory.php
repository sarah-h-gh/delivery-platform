<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => fn (array $attributes) => Product::factory()->create([
                'store_id' => Order::findOrFail($attributes['order_id'])->store_id,
            ])->id,
            'quantity' => fake()->numberBetween(1, 4),
            'unit_price' => fn (array $attributes) => Product::findOrFail($attributes['product_id'])->price,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item) {
            if (Product::findOrFail($item->product_id)->store_id !== Order::findOrFail($item->order_id)->store_id) {
                throw new \InvalidArgumentException('The product must belong to the order store.');
            }
        })->afterCreating(function (OrderItem $item) {
            $order = $item->order;
            $total = OrderFactory::cents((string) $order->delivery_fee);
            foreach ($order->items as $line) {
                $total += $line->quantity * OrderFactory::cents((string) $line->unit_price);
            }
            $order->forceFill(['total_price' => OrderFactory::money($total)])->save();
        });
    }
}
