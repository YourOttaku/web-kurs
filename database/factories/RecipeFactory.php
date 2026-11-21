<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

# @extends Factory<Recipe> 
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_meal_id' => (string) fake()->unique()->numberBetween(50000, 99999),
            'title' => fake()->sentence(3),
            'category_id' => Category::factory(),
            'instructions' => fake()->paragraphs(3, true),
            'image_url' => 'https://www.themealdb.com/images/media/meals/placeholder.jpg',
            'cooking_time_minutes' => fake()->numberBetween(10, 120),
            'total_ingredients_count' => 0,
        ];
    }

    # Рецепт з N новими інгредієнтами (або з переданими) + коректний лічильник
     # Приклад: Recipe::factory()->withIngredients(4)->create();
    public function withIngredients(int|iterable $ingredients = 5): static
    {
        return $this->afterCreating(function (Recipe $recipe) use ($ingredients) {
            $items = is_int($ingredients)
                ? Ingredient::factory()->count($ingredients)->create()
                : collect($ingredients);

            $recipe->ingredients()->attach(
                $items->mapWithKeys(fn (Ingredient $i) => [$i->id => [
                    'measure_raw' => '100g',
                    'amount' => 100,
                    'unit' => 'g',
                    'is_optional' => false,
                ]])->all()
            );

            $recipe->refreshIngredientsCount();
        });
    }
}
