<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    /** MySQL/MariaDB (Laragon) by default. DB_DRIVER=sqlite switches to a local file, used by the tests. */
    public static function fromEnv(string $root): PDO
    {
        if ((getenv('DB_DRIVER') ?: 'mysql') === 'sqlite') {
            return self::sqlite(getenv('DB_PATH') ?: $root . '/var/leaderboard.sqlite');
        }

        return self::mysql(
            getenv('DB_HOST') ?: '127.0.0.1',
            (int) (getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'escape_passcode',
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
        );
    }

    /** The table is created by database/schema.sql (run it in HeidiSQL). */
    public static function mysql(string $host, int $port, string $name, string $user, string $pass): PDO
    {
        return new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /** Self-contained file or ':memory:' database; creates its own table. */
    public static function sqlite(string $path): PDO
    {
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec("CREATE TABLE IF NOT EXISTS runs (
            id              TEXT PRIMARY KEY,
            player_name     TEXT NOT NULL,
            status          TEXT NOT NULL DEFAULT 'in_progress',
            current_case    INTEGER NOT NULL DEFAULT 0,
            case_started_at INTEGER,
            wrong_attempts  INTEGER NOT NULL DEFAULT 0,
            cases_solved    INTEGER NOT NULL DEFAULT 0,
            score           INTEGER NOT NULL DEFAULT 0,
            total_time_ms   INTEGER NOT NULL DEFAULT 0,
            created_at      INTEGER NOT NULL,
            finished_at     INTEGER
        )");

        return $pdo;
    }
}
