<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActiveOrders
{
    public function paginate(int|string|null $driverId = null): LengthAwarePaginator;
}
