<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;

final class SqliteConnection
{
    private static ?PDO $instance = null;

    public static function make(?string $databasePath = null): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $databasePath ??= dirname(__DIR__, 3) . '/storage/database.sqlite';
        $schemaPath = dirname(__DIR__, 3) . '/database/schema.sql';

        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Não foi possível criar o diretório do banco de controle: {$directory}");
        }

        try {
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA foreign_keys = ON;');

            if (is_readable($schemaPath)) {
                $pdo->exec((string) file_get_contents($schemaPath));
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Falha ao conectar no banco de controle (SQLite): ' . $e->getMessage(), 0, $e);
        }

        return self::$instance = $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
