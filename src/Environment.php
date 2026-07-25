<?php

declare(strict_types=1);

final class Environment
{
    private static array $loaded = [];

    public static function load(string $path = ''): void
    {
        if ($path === '') {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if (!array_key_exists($key, self::$loaded)) {
                    self::$loaded[$key] = $value;
                }
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (!self::$loaded && file_exists(dirname(__DIR__) . '/.env')) {
            self::load();
        }

        return self::$loaded[$key] ?? $_ENV[$key] ?? $default;
    }
}
