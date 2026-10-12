<?php

namespace App\Providers;

use App\Contracts\ActiveOrders;
use App\Services\EloquentActiveOrders;
use App\Services\OrderStatusPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ActiveOrders::class, EloquentActiveOrders::class);
        $this->app->singleton(OrderStatusPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading($this->app->environment(['local', 'development']));
    }
}
