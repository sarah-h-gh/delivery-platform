<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->randomElement(['Chicken Shawarma', 'Falafel Wrap', 'Hummus', 'Fresh Bread', 'Greek Salad', 'Orange Juice', 'Mineral Water', 'Rice Bowl', 'Grilled Chicken', 'Lentil Soup', 'Yogurt', 'Apples', 'Bananas', 'Milk', 'Eggs']),
            'price' => OrderFactory::money(fake()->numberBetween(150, 2500)),
        ];
    }
}
