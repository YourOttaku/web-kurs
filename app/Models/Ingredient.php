<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'normalized_name', 'unit_default'];

     #єдине місце нормалізації назви: нижній регістр + стиснуті пробіли
    # імпортер використовує його для пошуку існуючого інгредієнта
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    protected static function booted(): void
    {
        static::saving(function (Ingredient $ingredient) {
            $ingredient->normalized_name = static::normalize($ingredient->name);
        });
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'ingredient_recipe')
            ->withPivot(['measure_raw', 'amount', 'unit', 'is_optional'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_pantry')->withTimestamps();
    }
}
