<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeliveryPlatformSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliveryPlatformSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function cents(string $amount): int
    {
        $parts = explode('.', $amount);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function test_dataset_has_valid_relationships_statuses_and_totals(): void
    {
        $this->seed(DeliveryPlatformSeeder::class);
        $this->assertSame(1000, Order::count());
        $this->assertSame(201, User::count());
        $this->assertSame(40, Driver::count());
        $this->assertSame(30, Store::withoutGlobalScopes()->count());
        $this->assertSame(25, Store::count());
        $this->assertSame(5, Store::withoutGlobalScopes()->where('is_suspended', true)->count());
        $this->assertSame(450, Product::count());
        $this->assertGreaterThanOrEqual(1000, OrderItem::count());
        $this->assertLessThanOrEqual(5000, OrderItem::count());
        foreach (['delivered' => 650, 'cancelled' => 100, 'pending' => 100, 'accepted' => 100, 'picked_up' => 50] as $status => $count) {
            $this->assertSame($count, Order::where('status', $status)->count());
        }
        foreach (Order::with(['user', 'driver', 'items.product'])->get() as $order) {
            $this->assertNotNull($order->user);
            $store = Store::withoutGlobalScopes()->findOrFail($order->store_id);
            if (in_array($order->status, [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::PickedUp], true)) {
                $this->assertFalse((bool) $store->is_suspended);
            }
            if ($order->status === OrderStatus::Pending) {
                $this->assertNull($order->driver_id);
            }
            if (in_array($order->status, [OrderStatus::Accepted, OrderStatus::PickedUp, OrderStatus::Delivered], true)) {
                $this->assertNotNull($order->driver);
            }
            if ($order->driver_id !== null) {
                $this->assertNotNull($order->driver);
            }
            $this->assertCount($order->items->count(), $order->items->unique('product_id'));
            $this->assertGreaterThanOrEqual(1, $order->items->count());
            $expected = $this->cents((string) $order->delivery_fee);
            foreach ($order->items as $item) {
                $this->assertSame($order->id, $item->order_id);
                $this->assertNotNull($item->product);
                $this->assertSame($order->store_id, $item->product->store_id);
                $this->assertGreaterThanOrEqual(1, $item->quantity);
                $this->assertLessThanOrEqual(4, $item->quantity);
                $this->assertSame($this->cents((string) $item->product->price), $this->cents((string) $item->unit_price));
                $expected += $item->quantity * $this->cents((string) $item->unit_price);
            }
            $this->assertSame($expected, $this->cents((string) $order->total_price));
            $this->assertTrue($order->updated_at >= $order->created_at);
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_reruns_preserve_all_existing_data_without_duplicating_dataset(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'name' => 'Existing Customer']);
        $store = Store::forceCreate(['name' => 'Existing Store', 'is_suspended' => true]);
        $product = Product::forceCreate(['store_id' => $store->id, 'name' => 'Existing Product', 'price' => '12.34']);
        $driver = Driver::forceCreate(['name' => 'Existing Driver']);
        $order = Order::forceCreate(['user_id' => $user->id, 'store_id' => $store->id, 'driver_id' => $driver->id, 'status' => 'delivered', 'delivery_fee' => '1.00', 'total_price' => '25.68']);
        OrderItem::forceCreate(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => '12.34']);
        $tables = ['users', 'stores', 'products', 'drivers', 'orders', 'order_items'];
        $original = [];
        foreach ($tables as $table) {
            $original[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1001, Order::count());
        foreach ($tables as $table) {
            $this->assertSame($original[$table], DB::table($table)->where('id', 1)->get()->toJson());
        }
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->seed(DatabaseSeeder::class);
        foreach ($tables as $table) {
            $this->assertSame($snapshot[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_item_factory_uses_the_order_store_and_updates_total(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 3]);
        $this->assertSame($item->order->store_id, $item->product->store_id);
        $expected = 3 * $this->cents((string) $item->unit_price) + $this->cents((string) $item->order->delivery_fee);
        $this->assertSame($expected, $this->cents((string) $item->order->total_price));
    }

    public function test_item_factory_rejects_products_from_another_store(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);
    }
}
