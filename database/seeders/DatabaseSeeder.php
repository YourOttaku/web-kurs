<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            IngredientSeeder::class,
            DemoRecipeSeeder::class,
        ]);

        # ідемпотентно: повторний `db:seed` не падає на унікальному email
        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        # демо-холодильник, щоб одразу можна було перевірити «Розумний холодильник»
        $pantryIds = Ingredient::query()
            ->whereIn('normalized_name', ['chicken', 'garlic', 'salt', 'olive oil', 'potatoes', 'butter'])
            ->pluck('id');

        $user->pantry()->syncWithoutDetaching($pantryIds);
    }
}
