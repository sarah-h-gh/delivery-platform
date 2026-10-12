<?php

namespace App\Models\Concerns;

use App\Services\OrderStatusPolicy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasOrderActivity
{
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn(
            $query->getModel()->qualifyColumn('status'),
            app(OrderStatusPolicy::class)->activeValues(),
        );
    }

    public function isActive(): bool
    {
        return $this->status !== null
            && app(OrderStatusPolicy::class)->isActive($this->status);
    }
}
