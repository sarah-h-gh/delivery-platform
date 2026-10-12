<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class LazyLoadingConfigurationTest extends TestCase
{
    public function test_lazy_loading_prevention_is_enabled_only_for_local_and_development(): void
    {
        $environment = $this->app->environment();
        $prevented = Model::preventsLazyLoading();

        try {
            foreach (['local' => true, 'development' => true, 'production' => false, 'staging' => false, 'testing' => false] as $name => $expected) {
                $this->app->instance('env', $name);
                (new AppServiceProvider($this->app))->boot();

                $this->assertSame($expected, Model::preventsLazyLoading(), $name);
            }
        } finally {
            $this->app->instance('env', $environment);
            Model::preventLazyLoading($prevented);
        }
    }
}
