<?php

declare(strict_types=1);

namespace Goat\Support;

final class GoatConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config($key, $default);
            return $value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function string(string $key, string $default = ''): string
    {
        $val = self::get($key, $default);
        return is_string($val) && $val !== '' ? $val : $default;
    }

    public static function bool(string $key, bool $default = true): bool
    {
        $val = self::get($key, $default);
        if ($val === null) {
            return $default;
        }
        return (bool) $val;
    }
}
