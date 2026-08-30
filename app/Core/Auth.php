<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $login, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = :username OR email = :email) LIMIT 1');
        $stmt->execute(['username' => $login, 'email' => $login]);
        $user = $stmt->fetch();
        $now = new \DateTimeImmutable();

        if (!$user || $user['status'] !== 'active' || ($user['locked_until'] && new \DateTimeImmutable($user['locked_until']) > $now) || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                self::recordFailure($pdo, $user);
            }
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        Authorization::forget();
        $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        self::$user = null;
        self::audit('auth.login', 'users', (string) $user['id']);
        return true;
    }

    private static function recordFailure(PDO $pdo, array $user): void
    {
        $attempts = (int) $user['failed_login_attempts'] + 1;
        $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?')->execute([$attempts, $lockedUntil, $user['id']]);
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT id, username, email, display_name, status, password_changed_at FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        self::$user = $stmt->fetch() ?: null;
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requiresPasswordChange(): bool
    {
        return self::check() && self::user()['password_changed_at'] === null;
    }

    public static function replacePassword(string $password): void
    {
        self::requireLogin();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([self::user()['id']]);
        $currentHash = (string) $stmt->fetchColumn();
        if ($currentHash !== '' && password_verify($password, $currentHash)) {
            throw new \InvalidArgumentException('Choose a password different from the temporary password.');
        }
        $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),failed_login_attempts=0,locked_until=NULL WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), self::user()['id']]);
        self::audit('auth.password_changed', 'users', (string) self::user()['id']);
        self::$user = null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::redirect('/login');
        }
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::audit('auth.logout', 'users', (string) self::user()['id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function audit(string $action, ?string $entityType = null, ?string $entityId = null, ?array $old = null, ?array $new = null): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user_id'] ?? null, $action, $entityType, $entityId,
            $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
