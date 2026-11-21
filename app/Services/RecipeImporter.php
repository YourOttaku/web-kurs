<?php

namespace App\Services;

use App\Exceptions\TheMealDbException;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\MeasureParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

# зберігає дані TheMealDB (у форматі TheMealDbClient) у реляційну схему:
# категорії, рецепти, унікальні інгредієнти та M:N-зв'язки з pivot-полями
class RecipeImporter
{
    public function __construct(private readonly TheMealDbClient $client)
    {
    }

     # завантажує категорії (categories.php) і повертає їхні назви
     # @return list<string>

    public function importCategories(): array
    {
        $names = [];

        foreach ($this->client->getCategories() as $item) {
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            Category::updateOrCreate(['name' => $name], ['description' => $item['description'] ?? null]);
            $names[] = $name;
        }

        return $names;
    }

    # заповнює довідник інгредієнтів (list.php). Повертає кількість нових записів

    public function importIngredientCatalog(): int
    {
        $created = 0;

        foreach ($this->client->getAllIngredients() as $item) {
            if ($this->findOrCreateIngredient((string) $item['name'])->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    # імпортує страви однієї категорії: filter.php -> lookup.php для кожної страви
    # помилка однієї страви не зупиняє імпорт решти
    # @param  callable(int, int): void|null  $progress  викликається після кожної страви (оброблено, всього)
    # @return array{created: int, updated: int, failed: int}
    public function importCategoryMeals(string $category, ?int $limit = null, int $delayMs = 0, ?callable $progress = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'failed' => 0];

        $meals = $this->client->getMealsByCategory($category);

        if ($limit !== null) {
            $meals = array_slice($meals, 0, $limit);
        }

        $total = count($meals);

        foreach ($meals as $index => $short) {
            try {
                $meal = $this->client->getMealById($short['external_meal_id']);

                if ($meal === null) {
                    throw new InvalidArgumentException("Страву {$short['external_meal_id']} не знайдено в lookup.php");
                }

                if (empty($meal['category'])) {
                    $meal['category'] = $category;
                }

                $recipe = $this->importMeal($meal);
                $recipe->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            } catch (TheMealDbException|InvalidArgumentException $e) {
                $stats['failed']++;

                Log::warning('Не вдалося імпортувати страву', [
                    'meal_id' => $short['external_meal_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($progress !== null) {
                $progress($index + 1, $total);
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return $stats;
    }

    # зберігає одну страву (формат TheMealDbClient::getMealById) однією транзакцією
    # повторний імпорт тієї ж страви оновлює її, а не дублює
    #
    # @param  array{external_meal_id: string, title: string, category?: ?string, instructions?: ?string, image_url?: ?string, ingredients?: array<int, array{name: string, measure_raw?: ?string}>}  $meal

    public function importMeal(array $meal): Recipe
    {
        $externalId = trim((string) ($meal['external_meal_id'] ?? ''));
        $title = trim((string) ($meal['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw new InvalidArgumentException('Страва без idMeal або назви');
        }

        return DB::transaction(function () use ($meal, $externalId, $title) {
            $category = Category::firstOrCreate([
                'name' => trim((string) ($meal['category'] ?? '')) ?: 'Miscellaneous',
            ]);

            $recipe = Recipe::updateOrCreate(
                ['external_meal_id' => $externalId],
                [
                    'title' => $title,
                    'category_id' => $category->id,
                    'instructions' => trim((string) ($meal['instructions'] ?? '')) ?: '—',
                    'image_url' => $meal['image_url'] ?? null,
                ]
            );

            # detach + attach робить імпорт ідемпотентним: повторний запуск не множить зв'язки
            $recipe->ingredients()->detach();
            $recipe->ingredients()->attach($this->buildPivotPayload($meal['ingredients'] ?? []));
            $recipe->refreshIngredientsCount();

            return $recipe;
        });
    }

    # нормалізує інгредієнти страви та готує дані для attach() у форматі [ingredient_id => pivot]
    # той самий інгредієнт двічі в одній страві зливається в один рядок (унікальний індекс pivot)
    # @param  array<int, array{name: string, measure_raw?: ?string}>  $items
    # @return array<int, array<string, mixed>>
    private function buildPivotPayload(array $items): array
    {
        $payload = [];

        foreach ($items as $item) {
            $name = Str::squish((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $ingredient = $this->findOrCreateIngredient($name);
            $measureRaw = Str::squish((string) ($item['measure_raw'] ?? ''));
            [$amount, $unit] = MeasureParser::parse($measureRaw);

            $row = [
                'measure_raw' => $measureRaw !== '' ? mb_substr($measureRaw, 0, 255) : null,
                'amount' => $amount,
                'unit' => $unit,
                'is_optional' => (bool) preg_match('/optional/iu', $measureRaw.' '.$name),
            ];

            if (isset($payload[$ingredient->id])) {
                $row = $this->mergeRows($payload[$ingredient->id], $row);
            }

            $payload[$ingredient->id] = $row;
        }

        return $payload;
    }

    #злиття двох записів одного інгредієнта: тексти міри об'єднуються, кількості додаються, якщо одиниці збігаються 
    # @param  array<string, mixed>  $a 
    # @param  array<string, mixed>  $b 
    # @return array<string, mixed>
    private function mergeRows(array $a, array $b): array
    {
        $raw = collect([$a['measure_raw'], $b['measure_raw']])->filter()->implode(', ');

        if ($a['amount'] !== null && $b['amount'] !== null && $a['unit'] === $b['unit']) {
            $a['amount'] = round($a['amount'] + $b['amount'], 2);
        }

        $a['measure_raw'] = $raw !== '' ? mb_substr($raw, 0, 255) : null;
        $a['is_optional'] = $a['is_optional'] && $b['is_optional'];

        return $a;
    }

    # шукає інгредієнт за normalized_name, інакше створює: "Garlic", " garlic " і "GARLIC" — це один запис
    private function findOrCreateIngredient(string $name): Ingredient
    {
        $name = Str::squish($name);

        return Ingredient::firstOrCreate(
            ['normalized_name' => Ingredient::normalize($name)],
            ['name' => $name]
        );
    }
}
