<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

# @extends Factory<Ingredient> 
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()) . ' ' . fake()->unique()->numberBetween(1, 99999),
            'unit_default' => fake()->randomElement(['g', 'ml', 'pcs', null]),
        ];
    }
}
