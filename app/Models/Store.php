<?php

namespace App\Models;

use App\Models\Scopes\NotSuspendedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new NotSuspendedScope);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'store_id');
    }
}
