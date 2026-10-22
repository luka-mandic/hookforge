<?php

namespace App\Services\Concerns;

use InvalidArgumentException;

/**
 * Typed access to validated request attributes, which reach services as array<string, mixed>.
 */
trait ReadsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requiredString(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException("Attribute [{$key}] must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function optionalString(array $attributes, string $key, ?string $default = null): ?string
    {
        $value = $attributes[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
