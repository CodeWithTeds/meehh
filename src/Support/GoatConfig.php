<?php

declare(strict_types=1);

namespace Goat\Support;

final class GoatConfig
{
    /** @var array<string,mixed> runtime overrides (from --paths / --module / interactive) */
    private static array $overrides = [];

    public static function set(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
        if (function_exists('config')) {
            try { \config([$key => $value]); } catch (\Throwable) {}
        }
    }

    public static function clear(): void
    {
        self::$overrides = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            $v = self::$overrides[$key];
            return $v ?? $default;
        }

        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = \config($key, $default);
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
