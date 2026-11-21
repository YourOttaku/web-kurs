<?php

namespace App\Console\Commands;

use App\Exceptions\TheMealDbException;
use App\Services\RecipeImporter;
use Illuminate\Console\Command;

class ImportExternalRecipes extends Command
{
    protected $signature = 'recipes:import-external
        {--category= : Категорія TheMealDB (наприклад, Seafood). Без параметра імпортуються всі категорії}
        {--limit= : Максимум страв на одну категорію (для швидкого тесту)}
        {--delay=200 : Пауза між запитами до API, мс}
        {--with-catalog : Додатково завантажити довідник усіх інгредієнтів (list.php)}';

    protected $description = 'Імпорт рецептів із TheMealDB з нормалізацією інгредієнтів';

    public function handle(RecipeImporter $importer): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) $this->option('delay'));

        try {
            $this->info('Завантажую категорії...');
            $available = $importer->importCategories();
            $this->line('Категорій у БД оновлено: '.count($available));

            if ($this->option('with-catalog')) {
                $this->info('Завантажую довідник інгредієнтів...');
                $this->line('Нових інгредієнтів: '.$importer->importIngredientCatalog());
            }

            $categories = $this->resolveCategories($available);

            if ($categories === null) {
                return self::FAILURE;
            }

            $totals = ['created' => 0, 'updated' => 0, 'failed' => 0];

            foreach ($categories as $category) {
                $this->info("Категорія: {$category}");

                $bar = null;
                $stats = $importer->importCategoryMeals($category, $limit, $delay, function (int $done, int $total) use (&$bar) {
                    $bar ??= $this->output->createProgressBar($total);
                    $bar->advance();
                });

                $bar?->finish();
                $this->newLine();

                foreach ($stats as $key => $value) {
                    $totals[$key] += $value;
                }
            }
        } catch (TheMealDbException $e) {
            $this->error('Помилка API: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Створено рецептів', 'Оновлено', 'З помилкою'],
            [[$totals['created'], $totals['updated'], $totals['failed']]]
        );

        if ($totals['failed'] > 0) {
            $this->warn('Частину страв пропущено, деталі в storage/logs/laravel.log');
        }

        return self::SUCCESS;
    }

    # @param  list<string>  $available
     # @return list<string>|null null, якщо вказану категорію не знайдено
    private function resolveCategories(array $available): ?array
    {
        $requested = trim((string) $this->option('category'));

        if ($requested === '') {
            return $available;
        }

        foreach ($available as $name) {
            if (mb_strtolower($name) === mb_strtolower($requested)) {
                return [$name];
            }
        }

        $this->error("Невідома категорія «{$requested}». Доступні: ".implode(', ', $available));

        return null;
    }
}
