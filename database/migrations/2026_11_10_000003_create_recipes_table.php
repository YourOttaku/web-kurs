<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('external_meal_id')->nullable()->unique(); # idMeal з TheMealDB 
            $table->string('title');
            $table->foreignId('category_id')->constrained()->restrictOnDelete(); # індекс створюється автоматично
            $table->text('instructions');
            $table->string('image_url', 500)->nullable();
            $table->unsignedSmallInteger('cooking_time_minutes')->nullable();
            $table->unsignedSmallInteger('total_ingredients_count')->default(0);
            $table->timestamps();

            $table->index('total_ingredients_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
