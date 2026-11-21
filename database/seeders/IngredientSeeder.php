<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        # [назва, одиниця за замовчуванням]
        $items = [
            ['Salt', 'g'], ['Sugar', 'g'], ['Flour', 'g'], ['Butter', 'g'],
            ['Eggs', 'pcs'], ['Milk', 'ml'], ['Onion', 'pcs'], ['Garlic', 'pcs'],
            ['Potatoes', 'g'], ['Chicken', 'g'], ['Tomatoes', 'pcs'],
            ['Olive Oil', 'ml'], ['Black Pepper', 'g'], ['Water', 'ml'],
            ['Rice', 'g'], ['Cream', 'ml'], ['Cheese', 'g'], ['Carrots', 'pcs'],
            ['Lemon', 'pcs'], ['Parsley', 'g'],
        ];

        foreach ($items as [$name, $unit]) {
            // ідемпотентно: повторний запуск не створює дублів
            Ingredient::firstOrCreate(
                ['normalized_name' => Ingredient::normalize($name)],
                ['name' => $name, 'unit_default' => $unit]
            );
        }
    }
}
