<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    private const LEGACY_MIGRATIONS = [
        '001_tuition.sql',
        '002_insurance.sql',
        '003_uniform_id_inventory.sql',
        '004_inventory_other_type.sql',
        '005_tuition_fee_components.sql',
        '006_grade_tuition_assessments.sql',
        '007_tuition_payment_category.sql',
        '008_logo_position.sql',
        '009_student_profile_access.sql',
        '010_profile_accounts.sql',
        '011_teacher_profile_access.sql',
        '012_registrar_access.sql',
    ];

    public static function applyPending(PDO $pdo): array
    {
        self::ensureTable($pdo);
        self::baselineLegacyInstall($pdo);

        $applied = array_fill_keys($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
        $completed = [];
        foreach (self::files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Unable to read migration {$name}.");
            }
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
            $completed[] = $name;
        }
        return $completed;
    }

    public static function markAllApplied(PDO $pdo): void
    {
        self::ensureTable($pdo);
        $statement = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
        foreach (self::files() as $file) {
            $statement->execute([basename($file)]);
        }
    }

    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(191) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    }

    private static function baselineLegacyInstall(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() !== 0) {
            return;
        }
        $usersTable = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn();
        if (!(int) $usersTable) {
            return;
        }
        $statement = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
        foreach (self::LEGACY_MIGRATIONS as $migration) {
            $statement->execute([$migration]);
        }
    }

    private static function files(): array
    {
        $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }
}
