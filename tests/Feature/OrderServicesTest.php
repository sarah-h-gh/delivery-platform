<?php

namespace Tests\Feature;

use App\Contracts\ActiveOrders;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\EloquentActiveOrders;
use App\Services\OrderStatusPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class OrderServicesTest extends TestCase
{
    public function test_interface_resolves_to_the_eloquent_service_with_transient_bindings(): void
    {
        $first = $this->app->make(ActiveOrders::class);
        $second = $this->app->make(ActiveOrders::class);

        $this->assertInstanceOf(EloquentActiveOrders::class, $first);
        $this->assertInstanceOf(ActiveOrders::class, $first);
        $this->assertNotSame($first, $second);
        $this->assertFalse($this->app->isShared(ActiveOrders::class));
    }

    public function test_status_policy_is_a_singleton(): void
    {
        $first = $this->app->make(OrderStatusPolicy::class);
        $second = $this->app->make(OrderStatusPolicy::class);

        $this->assertSame($first, $second);
        $this->assertTrue($this->app->isShared(OrderStatusPolicy::class));
        $this->assertSame(['pending', 'accepted', 'picked_up'], $first->activeValues());
    }

    public function test_trait_classifies_every_status_and_builds_the_qualified_active_scope(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $order = (new Order)->forceFill(['status' => $status]);
            $expected = in_array($status, [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::PickedUp], true);

            $this->assertSame($expected, $order->isActive(), $status->value);
        }

        $this->assertFalse((new Order)->isActive());
        $query = Order::active();
        $this->assertSame(['pending', 'accepted', 'picked_up'], $query->getBindings());
        $this->assertStringContainsString('"orders"."status" in (?, ?, ?)', $query->toSql());
    }

    public function test_trait_uses_the_container_policy_for_both_scope_and_instance_checks(): void
    {
        $policy = Mockery::mock(OrderStatusPolicy::class);
        $policy->shouldReceive('activeValues')->once()->andReturn(['accepted']);
        $policy->shouldReceive('isActive')->once()->with(OrderStatus::Accepted)->andReturn(true);
        $this->app->instance(OrderStatusPolicy::class, $policy);

        $this->assertSame(['accepted'], Order::active()->getBindings());
        $this->assertTrue((new Order)->forceFill(['status' => OrderStatus::Accepted])->isActive());
    }

    public function test_api_uses_the_injected_interface(): void
    {
        $service = Mockery::mock(ActiveOrders::class);
        $service->shouldReceive('paginate')->once()->with(null)
            ->andReturn(new LengthAwarePaginator([], 0, 20));
        $this->app->instance(ActiveOrders::class, $service);

        $this->getJson('/api/orders/active')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 0);
    }
}
