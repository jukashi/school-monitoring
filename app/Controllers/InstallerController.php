<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Migrator;
use App\Core\View;
use PDO;
use Throwable;

final class InstallerController
{
    private const LOCK_FILE = APP_ROOT . '/storage/installed.lock';

    public function show(): void
    {
        if ($this->installed()) {
            View::render('errors/message', ['title' => 'Already installed', 'message' => 'The system is already installed. Sign in to continue.']);
            return;
        }
        View::render('installer/index', ['errors' => [], 'values' => $this->defaults()], 'layouts/guest');
    }

    public function install(): void
    {
        if ($this->installed()) {
            http_response_code(403);
            View::render('errors/message', ['title' => 'Installer locked', 'message' => 'Remove the installation lock manually only if you intend to reinstall.']);
            return;
        }

        $values = [
            'app_url' => trim($_POST['app_url'] ?? ''),
            'db_host' => trim($_POST['db_host'] ?? ''),
            'db_port' => trim($_POST['db_port'] ?? ''),
            'db_name' => trim($_POST['db_name'] ?? ''),
            'db_user' => trim($_POST['db_user'] ?? ''),
            'admin_name' => trim($_POST['admin_name'] ?? ''),
            'admin_username' => trim($_POST['admin_username'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
        ];
        $password = (string) ($_POST['admin_password'] ?? '');
        $dbPassword = (string) ($_POST['db_pass'] ?? '');
        $errors = $this->validate($values, $password);
        if ($errors) {
            View::render('installer/index', compact('errors', 'values'), 'layouts/guest');
            return;
        }

        try {
            $config = ['host' => $values['db_host'], 'port' => $values['db_port'], 'name' => $values['db_name'], 'user' => $values['db_user'], 'pass' => $dbPassword];
            $server = Database::connection(true, $config);
            $databaseName = str_replace('`', '``', $values['db_name']);
            $server->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = Database::connection(false, $config);
            $this->runSchema($pdo);
            Migrator::markAllApplied($pdo);
            $this->seed($pdo, $values, $password);
            $this->writeEnvironment($values, $dbPassword);
            if (!is_dir(APP_ROOT . '/storage')) {
                mkdir(APP_ROOT . '/storage', 0775, true);
            }
            file_put_contents(self::LOCK_FILE, 'Installed at ' . date(DATE_ATOM) . PHP_EOL, LOCK_EX);
            flash('success', 'Installation complete. Sign in with the administrator account you created.');
            header('Location: ' . rtrim($values['app_url'], '/') . '/login');
        } catch (Throwable $exception) {
            $errors[] = 'Installation failed: ' . $exception->getMessage();
            View::render('installer/index', compact('errors', 'values'), 'layouts/guest');
        }
    }

    private function runSchema(PDO $pdo): void
    {
        $sql = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
        $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS school_monitoring.*?;/si', '', $sql);
        $sql = preg_replace('/USE school_monitoring\s*;/i', '', $sql);
        $pdo->exec($sql);
    }

    private function seed(PDO $pdo, array $values, string $password): void
    {
        $permissions = require APP_ROOT . '/config/permissions.php';
        $roleDefaults = require APP_ROOT . '/config/role_defaults.php';
        $pdo->beginTransaction();
        try {
            $permissionStmt = $pdo->prepare('INSERT IGNORE INTO permissions (code, module, action, description) VALUES (?, ?, ?, ?)');
            foreach ($permissions as $code => $description) {
                [$module, $action] = explode('.', $code, 2);
                $permissionStmt->execute([$code, $module, $action, $description]);
            }
            $roleInsert = $pdo->prepare('INSERT INTO roles (name, description, is_system) VALUES (?, ?, ?)');
            $grant = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?');
            $roleIds = [];
            foreach ($roleDefaults as $roleName => $definition) {
                $roleInsert->execute([$roleName, $definition['description'], $roleName === 'Super Administrator' ? 1 : 0]);
                $roleIds[$roleName] = (int) $pdo->lastInsertId();
                $codes = $definition['permissions'] === ['*'] ? array_keys($permissions) : $definition['permissions'];
                foreach ($codes as $code) $grant->execute([$roleIds[$roleName], $code]);
            }
            $roleId = $roleIds['Super Administrator'];
            $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, password_changed_at) VALUES (?, ?, ?, ?, NOW())')
                ->execute([$values['admin_username'], $values['admin_email'] ?: null, password_hash($password, PASSWORD_DEFAULT), $values['admin_name']]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)')->execute([$userId, $roleId, $userId]);
            $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, value_type, is_public, updated_by) VALUES (?, ?, ?, ?, ?)')
                ->execute(['school_name', 'My School', 'string', 1, $userId]);
            $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, value_type, is_public, updated_by) VALUES (?, ?, ?, ?, ?)')
                ->execute(['currency_symbol', 'PHP', 'string', 1, $userId]);
            $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, value_type, is_public, updated_by) VALUES (?, ?, ?, ?, ?)')
                ->execute(['school_logo_position_y', '50', 'integer', 1, $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function writeEnvironment(array $values, string $dbPassword): void
    {
        $pairs = [
            'APP_NAME' => 'School Monitoring System', 'APP_URL' => rtrim($values['app_url'], '/'),
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_TIMEZONE' => 'Asia/Taipei',
            'DB_HOST' => $values['db_host'], 'DB_PORT' => $values['db_port'], 'DB_NAME' => $values['db_name'],
            'DB_USER' => $values['db_user'], 'DB_PASS' => $dbPassword, 'SESSION_NAME' => 'school_monitoring_session',
        ];
        $contents = '';
        foreach ($pairs as $key => $value) {
            $contents .= $key . '="' . addcslashes((string) $value, "\\\"") . '"' . PHP_EOL;
        }
        file_put_contents(APP_ROOT . '/.env', $contents, LOCK_EX);
    }

    private function validate(array $values, string $password): array
    {
        $errors = [];
        foreach (['app_url', 'db_host', 'db_port', 'db_name', 'db_user', 'admin_name', 'admin_username'] as $field) {
            if ($values[$field] === '') $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $values['db_name'])) $errors[] = 'Database name may contain only letters, numbers, and underscores.';
        if (!filter_var($values['app_url'], FILTER_VALIDATE_URL)) $errors[] = 'Application URL must be a valid URL.';
        if ($values['admin_email'] !== '' && !filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Administrator email is invalid.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $values['admin_username'])) $errors[] = 'Username must be 3-50 characters using letters, numbers, dot, dash, or underscore.';
        if (strlen($password) < 10) $errors[] = 'Administrator password must contain at least 10 characters.';
        if ($password !== ($_POST['admin_password_confirmation'] ?? '')) $errors[] = 'Administrator passwords do not match.';
        return $errors;
    }

    private function defaults(): array
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $appUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($scriptDir, '/');
        return ['app_url' => $appUrl, 'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'school_monitoring', 'db_user' => 'root', 'admin_name' => '', 'admin_username' => 'admin', 'admin_email' => ''];
    }

    private function installed(): bool
    {
        return is_file(self::LOCK_FILE);
    }
}
