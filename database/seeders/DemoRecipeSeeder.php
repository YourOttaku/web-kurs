<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

# демо-рецепти, щоб команда могла розробляти пошук без імпорту з API.
class DemoRecipeSeeder extends Seeder
{
    public function run(): void
    {
        $recipes = [
            ['DEMO-1', 'Garlic Chicken', 'Chicken', ['Chicken', 'Garlic', 'Olive Oil', 'Salt', 'Black Pepper']],
            ['DEMO-2', 'Mashed Potatoes', 'Side', ['Potatoes', 'Butter', 'Milk', 'Salt']],
            ['DEMO-3', 'Creamy Chicken Rice', 'Chicken', ['Chicken', 'Rice', 'Cream', 'Onion', 'Salt']],
            ['DEMO-4', 'Tomato Omelette', 'Breakfast', ['Eggs', 'Tomatoes', 'Butter', 'Salt']],
            ['DEMO-5', 'Simple Pancakes', 'Dessert', ['Flour', 'Eggs', 'Milk', 'Sugar', 'Butter']],
        ];

        foreach ($recipes as [$extId, $title, $categoryName, $ingredientNames]) {
            $recipe = Recipe::firstOrCreate(
                ['external_meal_id' => $extId],
                [
                    'title' => $title,
                    'category_id' => Category::firstOrCreate(['name' => $categoryName])->id,
                    'instructions' => 'Demo instructions for ' . $title . '.',
                ]
            );

            $ids = [];
            foreach ($ingredientNames as $name) {
                $ingredient = Ingredient::firstOrCreate(
                    ['normalized_name' => Ingredient::normalize($name)],
                    ['name' => $name]
                );
                $ids[$ingredient->id] = ['measure_raw' => 'to taste'];
            }

            $recipe->ingredients()->syncWithoutDetaching($ids);
            $recipe->refreshIngredientsCount();
        }
    }
}
