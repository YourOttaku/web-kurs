<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->string('measure_raw')->nullable();     # вихідний текст: "200g", "1 pinch"
            $table->decimal('amount', 8, 2)->nullable();
            $table->string('unit')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            # один інгредієнт — один раз у рецепті
            $table->unique(['recipe_id', 'ingredient_id']);
            # для пошуку "які рецепти містять інгредієнт" 
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_recipe');
    }
};
