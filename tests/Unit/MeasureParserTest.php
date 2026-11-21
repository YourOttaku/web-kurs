<?php

namespace Tests\Unit;

use App\Support\MeasureParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeasureParserTest extends TestCase
{
    #[DataProvider('measures')]
    public function test_it_parses_measure(string $raw, ?float $amount, ?string $unit): void
    {
        $this->assertSame([$amount, $unit], MeasureParser::parse($raw));
    }

    public static function measures(): array
    {
        return [
            'grams glued' => ['200g', 200.0, 'g'],
            'fraction' => ['3/4 cup', 0.75, 'cup'],
            'mixed number' => ['1 1/2 tsp', 1.5, 'tsp'],
            'unicode fraction' => ['1½ cups', 1.5, 'cup'],
            'only unicode fraction' => ['½ tsp', 0.5, 'tsp'],
            'plural unit' => ['2 cloves', 2.0, 'clove'],
            'unit case' => ['3 Tbsp', 3.0, 'tbsp'],
            'decimal comma' => ['1,5 kg', 1.5, 'kg'],
            'range takes first' => ['1-2 tbsp', 1.0, 'tbsp'],
            'double units' => ['200g/7oz', 200.0, 'g'],
            'unknown unit kept' => ['1 large', 1.0, 'large'],
            'number only' => ['2', 2.0, null],
            'word only' => ['Pinch', null, null],
            'to taste' => ['to taste', null, null],
            'empty' => ['', null, null],
            'spaces only' => ['   ', null, null],
        ];
    }
}
  