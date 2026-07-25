<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Environment::get('DB_HOST', '127.0.0.1');
        $port = Environment::get('DB_PORT', '3306');
        $database = Environment::get('DB_DATABASE', 'shortnurl');
        $username = Environment::get('DB_USERNAME', 'root');
        $password = Environment::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        self::$instance = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }
}
