<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveOrdersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Migrate only an isolated in-memory database; never refresh the project database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function order(OrderStatus $status, string $createdAt, ?Driver $driver = null, bool $suspended = false): Order
    {
        $user = User::factory()->create();
        $store = Store::forceCreate(['name' => 'Test Store', 'is_suspended' => $suspended]);

        return Order::forceCreate([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'driver_id' => $driver?->id,
            'status' => $status,
            'total_price' => 10,
            'created_at' => $createdAt,
        ]);
    }

    public function test_active_statuses_ordering_store_visibility_and_json(): void
    {
        $driver = Driver::forceCreate(['name' => 'Test Driver']);
        $newest = $this->order(OrderStatus::PickedUp, '2026-10-09 12:00:00', $driver);
        $oldest = $this->order(OrderStatus::Pending, '2026-10-09 10:00:00');
        $middle = $this->order(OrderStatus::Accepted, '2026-10-09 11:00:00', $driver);
        $this->order(OrderStatus::Delivered, '2026-10-09 08:00:00');
        $this->order(OrderStatus::Cancelled, '2026-10-09 08:00:00');
        foreach ([OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::PickedUp] as $status) {
            $this->order($status, '2026-10-09 09:00:00', $driver, true);
        }

        $product = Product::forceCreate(['store_id' => $oldest->store_id, 'name' => 'Product', 'price' => 5]);
        foreach ([3, 4] as $quantity) {
            OrderItem::forceCreate(['order_id' => $oldest->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 5]);
        }

        $response = $this->getJson('/api/orders/active')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'status', 'customer_name', 'store_name', 'driver_name', 'items_count', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.driver_name', null)
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.customer_name', $oldest->user->name)
            ->assertJsonPath('data.0.store_name', 'Test Store')
            ->assertJsonPath('data.2.driver_name', 'Test Driver')
            ->assertJsonPath('meta.per_page', 20);

        $this->assertSame([$oldest->id, $middle->id, $newest->id], array_column($response->json('data'), 'id'));
        $this->assertSame(['pending', 'accepted', 'picked_up'], array_column($response->json('data'), 'status'));
    }

    public function test_optional_driver_filter(): void
    {
        $driver = Driver::forceCreate(['name' => 'Selected Driver']);
        $other = Driver::forceCreate(['name' => 'Other Driver']);
        $selected = $this->order(OrderStatus::Accepted, '2026-10-09 10:00:00', $driver);
        $this->order(OrderStatus::Pending, '2026-10-09 09:00:00', $other);
        $this->order(OrderStatus::Pending, '2026-10-09 08:00:00');

        $this->getJson('/api/orders/active?driver_id='.$driver->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $selected->id);
    }

    public function test_driver_filter_must_be_an_existing_integer_id(): void
    {
        foreach (['999999', 'invalid', ''] as $value) {
            $this->getJson('/api/orders/active?driver_id='.$value)
                ->assertUnprocessable()->assertJsonValidationErrors('driver_id');
        }
    }

    public function test_results_are_paginated_in_groups_of_twenty(): void
    {
        $order = $this->order(OrderStatus::Pending, '2026-10-09 10:00:00');
        for ($i = 1; $i <= 20; $i++) {
            $copy = $order->replicate();
            $copy->created_at = $order->created_at->copy()->addMinutes($i);
            $copy->save();
        }

        $this->getJson('/api/orders/active')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21);
        $this->getJson('/api/orders/active?page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 2);
    }
}
