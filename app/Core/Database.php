<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(bool $withoutDatabase = false, ?array $override = null): PDO
    {
        if (!$withoutDatabase && $override === null && self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = $override ?? [
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME', 'school_monitoring'),
            'user' => Env::get('DB_USER', 'root'),
            'pass' => Env::get('DB_PASS', ''),
        ];
        $database = $withoutDatabase ? '' : ';dbname=' . $config['name'];
        $dsn = sprintf('mysql:host=%s;port=%s%s;charset=utf8mb4', $config['host'], $config['port'], $database);
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
        if (!$withoutDatabase && $override === null) {
            self::$connection = $pdo;
        }
        return $pdo;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
