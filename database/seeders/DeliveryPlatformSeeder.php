<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryPlatformSeeder extends Seeder
{
    public const MARKER_EMAIL = 'delivery-platform-seed-v1@example.test';

    public const MARKER_NAME = 'Delivery Platform Seed v1';

    public function run(): void
    {
        DB::transaction(function () {
            // This reserved user is written last: its presence marks an atomically completed dataset.
            $marker = User::where('email', self::MARKER_EMAIL)->first();
            if ($marker) {
                if ($marker->name !== self::MARKER_NAME) {
                    throw new \RuntimeException('The seed marker email is already in use.');
                }

                return;
            }

            $customers = User::factory()->count(200)->create();
            $drivers = Driver::factory()->count(40)->create();
            $stores = Store::factory()->count(25)->create()
                ->concat(Store::factory()->suspended()->count(5)->create());
            $activeStores = $stores->where('is_suspended', false)->values();
            $catalogs = [];
            foreach ($stores as $store) {
                $catalogs[$store->id] = Product::factory()->count(15)->create(['store_id' => $store->id]);
            }

            // Fixed coverage: 650 delivered, 100 cancelled, 100 pending, 100 accepted, 50 picked up.
            $statuses = array_merge(
                array_fill(0, 650, OrderStatus::Delivered),
                array_fill(0, 100, OrderStatus::Cancelled),
                array_fill(0, 100, OrderStatus::Pending),
                array_fill(0, 100, OrderStatus::Accepted),
                array_fill(0, 50, OrderStatus::PickedUp),
            );
            shuffle($statuses);
            foreach ($statuses as $status) {
                $active = in_array($status, [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::PickedUp], true);
                $store = ($active ? $activeStores : $stores)->random();
                $createdAt = $active ? fake()->dateTimeBetween('-2 hours', 'now') : fake()->dateTimeBetween('-90 days', '-1 day');
                $updatedAt = $active ? $createdAt : (clone $createdAt)->modify('+45 minutes');
                $fee = fake()->numberBetween(100, 600);
                $total = $fee;
                $lines = [];
                foreach ($catalogs[$store->id]->random(fake()->numberBetween(1, 5)) as $product) {
                    $quantity = fake()->numberBetween(1, 4);
                    $unitCents = OrderFactory::cents((string) $product->price);
                    $total += $quantity * $unitCents;
                    $lines[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => OrderFactory::money($unitCents),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
                $order = Order::factory()->create([
                    'user_id' => $customers->random()->id,
                    'store_id' => $store->id,
                    'driver_id' => $status === OrderStatus::Pending ? null
                        : ($status === OrderStatus::Cancelled && fake()->boolean() ? null : $drivers->random()->id),
                    'status' => $status,
                    'delivery_fee' => OrderFactory::money($fee),
                    'total_price' => OrderFactory::money($total),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
                // Seeder execution is unguarded by Laravel; no existing rows are updated.
                $order->items()->createMany($lines);
            }

            User::factory()->create(['name' => self::MARKER_NAME, 'email' => self::MARKER_EMAIL]);
        });
    }
}
