<?php

namespace App\Support;

# розбирає текстову міру з TheMealDB (strMeasureN) на кількість та одиницю
# "200g" -> [200.0, 'g'], "3/4 cup" -> [0.75, 'cup'], "1 1/2 tsp" -> [1.5, 'tsp'],
#"Pinch" / "to taste" -> [null, null] (вихідний текст усе одно лишається в measure_raw)

final class MeasureParser
{
    private const FRACTION_CHARS = [
        '½' => 0.5, '¼' => 0.25, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3, '⅛' => 0.125,
    ];

    # dідомі написання одиниць -> канонічна форма 
    private const UNITS = [
        'g' => 'g', 'gr' => 'g', 'gram' => 'g', 'grams' => 'g',
        'kg' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
        'ml' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml', 'milliliter' => 'ml', 'milliliters' => 'ml',
        'l' => 'l', 'litre' => 'l', 'litres' => 'l', 'liter' => 'l', 'liters' => 'l',
        'tsp' => 'tsp', 'tspn' => 'tsp', 'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'tbsp' => 'tbsp', 'tbs' => 'tbsp', 'tblsp' => 'tbsp', 'tbspn' => 'tbsp',
        'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp',
        'cup' => 'cup', 'cups' => 'cup',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
        'clove' => 'clove', 'cloves' => 'clove',
        'slice' => 'slice', 'slices' => 'slice',
        'can' => 'can', 'cans' => 'can', 'tin' => 'can', 'tins' => 'can',
        'pinch' => 'pinch', 'dash' => 'dash',
    ];

    # @return array{0: float|null, 1: string|null} [amount, unit]
    public static function parse(string $raw): array
    {
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));

        if ($raw === '') {
            return [null, null];
        }

        # число: "1 1/2" | "3/4" | "1.5" / "1,5" / "1_1/2" | "1/2"
        $number = '\d+\s\d+\/\d+|\d+\/\d+|\d+(?:[.,]\d+)?[½¼¾⅓⅔⅛]?|[½¼¾⅓⅔⅛]';
        # необов'язковий діапазон "1-2": береться перше число
        $pattern = '/^(?<qty>'.$number.')(?:\s*[-–]\s*(?:'.$number.'))?\s*(?<unit>.*)$/u';

        if (! preg_match($pattern, $raw, $m)) {
            return [null, null];
        }

        $amount = self::toFloat($m['qty']);

        # decimal(8, 2) вміщає максимум 999999.99
        if ($amount === null || $amount > 999999.99) {
            return [null, null];
        }

        return [round($amount, 2), self::normalizeUnit(trim($m['unit']))];
    }

    private static function toFloat(string $qty): ?float
    {
        $qty = str_replace(',', '.', trim($qty));

        // "1_1/2", "1/2"
        if (preg_match('/^(\d+(?:\.\d+)?)?([½¼¾⅓⅔⅛])$/u', $qty, $m)) {
            return (float) ($m[1] !== '' ? $m[1] : 0) + self::FRACTION_CHARS[$m[2]];
        }

        // "3/4", "1 1/2"
        if (str_contains($qty, '/')) {
            if (! preg_match('/^(?:(\d+)\s+)?(\d+)\/(\d+)$/', $qty, $m) || (int) $m[3] === 0) {
                return null;
            }

            return (float) ($m[1] !== '' ? $m[1] : 0) + ((int) $m[2] / (int) $m[3]);
        }

        return is_numeric($qty) ? (float) $qty : null;
    }

    private static function normalizeUnit(string $unit): ?string
    {
        if ($unit === '') {
            return null;
        }

        $unit = mb_strtolower($unit);

        # перше слово з літер: "g/7oz" -> "g", "tbsp." -> "tbsp"
        if (preg_match('/^\p{L}+/u', $unit, $w) && isset(self::UNITS[$w[0]])) {
            return self::UNITS[$w[0]];
        }

        # невідома одиниця ("large", "bunch of") лишається як є
        return mb_substr(rtrim($unit, " ,.;"), 0, 50);
    }
}
