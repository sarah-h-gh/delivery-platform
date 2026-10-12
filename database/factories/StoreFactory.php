<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company().' Market', 'is_suspended' => false];
    }

    public function suspended(): static
    {
        return $this->state(['is_suspended' => true]);
    }
}
