<?php

namespace App\Services;

use App\Exceptions\TheMealDbException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TheMealDbClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $url = rtrim((string) config('services.themealdb.base_url', 'https://www.themealdb.com/api/json/v1'), '/');
        $key = trim((string) config('services.themealdb.api_key', '1'));

        $this->baseUrl = "{$url}/{$key}";
        $this->timeout = (int) config('services.themealdb.timeout', 15);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->retry(3, 500, throw: false);
    }

    /**
     * @return list<array{
     *     external_id: ?string,
     *     name: string,
     *     image_url: ?string,
     *     description: ?string
     * }>
     */
    public function getCategories(): array
    {
        $data = $this->get('/categories.php');

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            return [];
        }

        $categories = [];

        foreach ($data['categories'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['strCategory'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rawId = $item['idCategory'] ?? null;

            $categories[] = [
                'external_id' => $rawId !== null && $rawId !== '' ? (string) $rawId : null,
                'name'        => $name,
                'image_url'   => $this->nullableString($item['strCategoryThumb'] ?? null),
                'description' => $this->nullableString($item['strCategoryDescription'] ?? null),
            ];
        }

        return $categories;
    }

    /**
     * @return list<array{
     *     external_meal_id: string,
     *     title: string,
     *     image_url: ?string
     * }>
     */
    public function getMealsByCategory(string $category): array
    {
        $category = trim($category);
        if ($category === '') {
            return [];
        }

        $data = $this->get('/filter.php', ['c' => $category]);

        if (empty($data['meals']) || !is_array($data['meals'])) {
            return [];
        }

        $meals = [];

        foreach ($data['meals'] as $meal) {
            if (!is_array($meal)) {
                continue;
            }

            $id = trim((string) ($meal['idMeal'] ?? ''));
            $title = trim((string) ($meal['strMeal'] ?? ''));

            if ($id === '' || $title === '') {
                continue;
            }

            $meals[] = [
                'external_meal_id' => $id,
                'title'            => $title,
                'image_url'        => $this->nullableString($meal['strMealThumb'] ?? null),
            ];
        }

        return $meals;
    }

    /**
     * @return ?array{
     *     external_meal_id: string,
     *     title: string,
     *     category: ?string,
     *     instructions: string,
     *     image_url: ?string,
     *     ingredients: list<array{name: string, measure_raw: ?string}>
     * }
     */
    public function getMealById(string $mealId): ?array
    {
        $mealId = trim($mealId);
        if ($mealId === '') {
            return null;
        }

        $data = $this->get('/lookup.php', ['i' => $mealId]);

        if (empty($data['meals'][0]) || !is_array($data['meals'][0])) {
            return null;
        }

        $rawMeal = $data['meals'][0];
        $id = trim((string) ($rawMeal['idMeal'] ?? ''));
        $title = trim((string) ($rawMeal['strMeal'] ?? ''));

        if ($id === '' || $title === '') {
            return null;
        }

        return [
            'external_meal_id' => $id,
            'title'            => $title,
            'category'         => $this->nullableString($rawMeal['strCategory'] ?? null),
            'instructions'     => trim((string) ($rawMeal['strInstructions'] ?? '')),
            'image_url'        => $this->nullableString($rawMeal['strMealThumb'] ?? null),
            'ingredients'      => $this->extractIngredients($rawMeal),
        ];
    }

    /**
     * @return list<array{
     *     external_id: ?string,
     *     name: string,
     *     description: ?string
     * }>
     */
    public function getAllIngredients(): array
    {
        $data = $this->get('/list.php', ['i' => 'list']);

        if (empty($data['meals']) || !is_array($data['meals'])) {
            return [];
        }

        $ingredients = [];

        foreach ($data['meals'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['strIngredient'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rawId = $item['idIngredient'] ?? null;

            $ingredients[] = [
                'external_id' => $rawId !== null && $rawId !== '' ? (string) $rawId : null,
                'name'        => $name,
                'description' => $this->nullableString($item['strDescription'] ?? null),
            ];
        }

        return $ingredients;
    }

    /**
     * @param  array<string, mixed>  $rawMeal
     * @return list<array{name: string, measure_raw: ?string}>
     */
    private function extractIngredients(array $rawMeal): array
    {
        $parsed = [];

        for ($i = 1; $i <= 20; $i++) {
            $ingredient = trim((string) ($rawMeal["strIngredient{$i}"] ?? ''));
            $measure = $this->nullableString($rawMeal["strMeasure{$i}"] ?? null);

            if ($ingredient === '') {
                continue;
            }

            $parsed[] = [
                'name'        => $ingredient,
                'measure_raw' => $measure,
            ];
        }

        return $parsed;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) && !is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client()->get($endpoint, $query);
        } catch (Throwable $e) {
            Log::error('TheMealDB connection error', [
                'endpoint' => $endpoint,
                'query'    => $query,
                'message'  => $e->getMessage(),
            ]);

            throw TheMealDbException::connectionFailed($endpoint, $e);
        }

        if ($response->failed()) {
            Log::warning('TheMealDB HTTP error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
            ]);

            throw TheMealDbException::requestFailed($endpoint, $response->status());
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw TheMealDbException::invalidPayload($endpoint);
        }

        return $json;
    }
}