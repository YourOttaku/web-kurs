<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_meal_id',
        'title',
        'category_id',
        'instructions',
        'image_url',
        'cooking_time_minutes',
        'total_ingredients_count',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_recipe')
            ->withPivot(['measure_raw', 'amount', 'unit', 'is_optional'])
            ->withTimestamps();
    }

    # перерахувати total_ingredients_count за фактичною кількістю рядків у pivot.
    # викликати після attach()/sync() (імпортер).
    public function refreshIngredientsCount(): void
    {
        $this->update(['total_ingredients_count' => $this->ingredients()->count()]);
    }
}
