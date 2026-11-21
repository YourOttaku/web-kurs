<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\RecipeImporter;
use App\Services\TheMealDbClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeImporterTest extends TestCase
{
    use RefreshDatabase;

#Страва у форматі TheMealDbClient::getMealById() 
    private function meal(array $overrides = []): array
    {
        return array_merge([
            'external_meal_id' => '52772',
            'title' => 'Teriyaki Chicken Casserole',
            'category' => 'Chicken',
            'instructions' => 'Mix everything and bake.',
            'image_url' => 'https://example.com/img.jpg',
            'ingredients' => [
                ['name' => 'Soy Sauce', 'measure_raw' => '3/4 cup'],
                ['name' => 'Chicken Breasts', 'measure_raw' => '2'],
                ['name' => 'Garlic', 'measure_raw' => '2 cloves'],
            ],
        ], $overrides);
    }

    private function importer(): RecipeImporter
    {
        return app(RecipeImporter::class);
    }

    public function test_it_saves_recipe_category_ingredients_and_counter(): void
    {
        $recipe = $this->importer()->importMeal($this->meal());

        $this->assertSame('Chicken', $recipe->category->name);
        $this->assertSame(3, $recipe->fresh()->total_ingredients_count);
        $this->assertCount(3, $recipe->ingredients);

        $garlic = $recipe->ingredients()->where('normalized_name', 'garlic')->first();
        $this->assertSame('2 cloves', $garlic->pivot->measure_raw);
        $this->assertEquals(2, (float) $garlic->pivot->amount);
        $this->assertSame('clove', $garlic->pivot->unit);
    }

    public function test_ingredients_with_different_case_are_not_duplicated(): void
    {
        $this->importer()->importMeal($this->meal());
        $this->importer()->importMeal($this->meal([
            'external_meal_id' => '1',
            'title' => 'Second',
            'ingredients' => [['name' => '  GARLIC ', 'measure_raw' => '1 clove']],
        ]));

        $this->assertSame(1, Ingredient::where('normalized_name', 'garlic')->count());
        $this->assertSame(2, Recipe::count());
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->importer()->importMeal($this->meal());
        $recipe = $this->importer()->importMeal($this->meal(['title' => 'Renamed']));

        $this->assertSame(1, Recipe::count());
        $this->assertSame('Renamed', $recipe->title);
        $this->assertSame(3, $recipe->fresh()->total_ingredients_count);
        $this->assertDatabaseCount('ingredient_recipe', 3);
    }

    public function test_same_ingredient_twice_in_one_meal_is_merged(): void
    {
        $recipe = $this->importer()->importMeal($this->meal([
            'ingredients' => [
                ['name' => 'Salt', 'measure_raw' => '1 tsp'],
                ['name' => 'salt', 'measure_raw' => '2 tsp'],
            ],
        ]));

        $this->assertSame(1, $recipe->fresh()->total_ingredients_count);

        $salt = $recipe->ingredients()->first();
        $this->assertEquals(3, (float) $salt->pivot->amount);
        $this->assertSame('1 tsp, 2 tsp', $salt->pivot->measure_raw);
    }

    public function test_empty_ingredient_slots_are_skipped(): void
    {
        $recipe = $this->importer()->importMeal($this->meal([
            'ingredients' => [
                ['name' => 'Salt', 'measure_raw' => ''],
                ['name' => '   ', 'measure_raw' => '1 cup'],
            ],
        ]));

        $this->assertSame(1, $recipe->fresh()->total_ingredients_count);
        $this->assertNull($recipe->ingredients()->first()->pivot->measure_raw);
    }

    public function test_command_imports_requested_category_with_mocked_client(): void
    {
        $this->mock(TheMealDbClient::class, function ($mock) {
            $mock->shouldReceive('getCategories')->once()->andReturn([
                ['external_id' => '1', 'name' => 'Chicken', 'image_url' => null, 'description' => 'Chicken dishes'],
                ['external_id' => '2', 'name' => 'Seafood', 'image_url' => null, 'description' => 'Fish'],
            ]);
            $mock->shouldReceive('getMealsByCategory')->with('Chicken')->once()->andReturn([
                ['external_meal_id' => '52772', 'title' => 'Teriyaki', 'image_url' => null],
            ]);
            $mock->shouldReceive('getMealById')->with('52772')->once()->andReturn($this->meal());
        });

        $this->artisan('recipes:import-external', ['--category' => 'chicken', '--delay' => 0])
            ->assertSuccessful();

        $this->assertDatabaseHas('recipes', ['external_meal_id' => '52772', 'total_ingredients_count' => 3]);
        $this->assertSame(2, Category::count());
    }

    public function test_command_fails_for_unknown_category(): void
    {
        $this->mock(TheMealDbClient::class, function ($mock) {
            $mock->shouldReceive('getCategories')->once()->andReturn([
                ['external_id' => '1', 'name' => 'Chicken', 'image_url' => null, 'description' => null],
            ]);
            $mock->shouldReceive('getMealsByCategory')->never();
        });

        $this->artisan('recipes:import-external', ['--category' => 'Nonexistent', '--delay' => 0])
            ->assertFailed();

        $this->assertDatabaseCount('recipes', 0);
    }
}
