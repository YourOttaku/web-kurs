<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TheMealDbException extends RuntimeException
{
    public static function requestFailed(string $endpoint, int $status): self
    {
        return new self("Помилка запиту до TheMealDB API ({$endpoint}). HTTP статус: {$status}");
    }

    public static function invalidPayload(string $endpoint): self
    {
        return new self("Отримано некоректну структуру JSON від TheMealDB API ({$endpoint})");
    }

    public static function connectionFailed(string $endpoint, Throwable $previous): self
    {
        return new self("Помилка з'єднання з TheMealDB API ({$endpoint}): {$previous->getMessage()}", 0, $previous);
    }
}