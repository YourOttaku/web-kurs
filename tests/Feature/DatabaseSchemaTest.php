<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_fields_are_available(): void
    {
        $recipe = Recipe::factory()->withIngredients(3)->create();

        $this->assertSame(3, $recipe->fresh()->total_ingredients_count);
        $this->assertSame('100g', $recipe->ingredients()->first()->pivot->measure_raw);
    }

    public function test_same_ingredient_cannot_be_attached_twice(): void
    {
        $recipe = Recipe::factory()->create();
        $ingredient = Ingredient::factory()->create();

        $recipe->ingredients()->attach($ingredient->id);

        $this->expectException(QueryException::class);
        $recipe->ingredients()->attach($ingredient->id);
    }

    public function test_ingredient_name_is_normalized(): void
    {
        $ingredient = Ingredient::create(['name' => '  Olive   OIL ']);

        $this->assertSame('olive oil', $ingredient->normalized_name);
    }

    public function test_category_slug_is_generated(): void
    {
        $category = Category::create(['name' => 'Side Dish']);

        $this->assertSame('side-dish', $category->slug);
    }

    public function test_deleting_recipe_cascades_to_pivot(): void
    {
        $recipe = Recipe::factory()->withIngredients(2)->create();

        $recipe->delete();

        $this->assertDatabaseCount('ingredient_recipe', 0);
        $this->assertDatabaseCount('ingredients', 2); #самі інгредієнти лишаються
    }

    public function test_pantry_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::factory()->create();

        $user->pantry()->attach($ingredient->id);

        $this->expectException(QueryException::class);
        $user->pantry()->attach($ingredient->id);
    }
}
