<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
       #Назви збігаються з strCategory у TheMealDB, щоб імпортер знаходив їх за name
        $names = [
            'Beef', 'Chicken', 'Dessert', 'Lamb', 'Miscellaneous', 'Pasta',
            'Pork', 'Seafood', 'Side', 'Starter', 'Vegan', 'Vegetarian',
            'Breakfast', 'Goat',
        ];

        foreach ($names as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
