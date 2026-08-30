<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class AccountProvisioner
{
    private const PROFILE_TABLES = ['students', 'teachers', 'employees'];

    public static function createForProfile(
        PDO $pdo,
        string $table,
        int $profileId,
        string $username,
        ?string $email,
        string $displayName,
        string $roleName
    ): array {
        self::assertProfileTable($table);
        $temporaryPassword = self::temporaryPassword();
        $email = self::availableEmail($pdo, $email);
        $stmt = $pdo->prepare(
            'INSERT INTO users(username,email,password_hash,display_name,status,password_changed_at)
             VALUES(?,?,?,?,"active",NULL)'
        );
        $stmt->execute([$username, $email, password_hash($temporaryPassword, PASSWORD_DEFAULT), $displayName]);
        $userId = (int) $pdo->lastInsertId();

        $role = $pdo->prepare('SELECT id FROM roles WHERE name=? LIMIT 1');
        $role->execute([$roleName]);
        $roleId = $role->fetchColumn();
        if ($roleId === false) throw new RuntimeException("Required role {$roleName} does not exist.");
        $pdo->prepare('INSERT INTO user_roles(user_id,role_id,assigned_by) VALUES(?,?,?)')
            ->execute([$userId, (int) $roleId, Auth::user()['id']]);
        $pdo->prepare("UPDATE {$table} SET user_id=? WHERE id=?")->execute([$userId, $profileId]);
        Auth::audit('users.auto_created', 'users', (string) $userId, null, [
            'username' => $username, 'role' => $roleName, 'profile_type' => $table, 'profile_id' => $profileId,
        ]);

        return ['username' => $username, 'temporary_password' => $temporaryPassword, 'user_id' => $userId];
    }

    public static function syncLinkedAccount(
        PDO $pdo,
        string $table,
        int $profileId,
        string $username,
        ?string $email,
        string $displayName
    ): void {
        self::assertProfileTable($table);
        $stmt = $pdo->prepare("SELECT user_id FROM {$table} WHERE id=?");
        $stmt->execute([$profileId]);
        $userId = $stmt->fetchColumn();
        if ($userId === false || $userId === null) return;
        $email = self::availableEmail($pdo, $email, (int) $userId);
        $pdo->prepare('UPDATE users SET username=?,email=?,display_name=? WHERE id=?')
            ->execute([$username, $email, $displayName, (int) $userId]);
    }

    public static function resetTemporaryPassword(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn();
        if ($username === false) throw new RuntimeException('User account does not exist.');
        $temporaryPassword = self::temporaryPassword();
        $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NULL,failed_login_attempts=0,locked_until=NULL WHERE id=?')
            ->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $userId]);
        Auth::audit('users.temporary_password_generated', 'users', (string) $userId, null, ['username' => $username]);
        return ['username' => (string) $username, 'temporary_password' => $temporaryPassword, 'user_id' => $userId];
    }

    private static function availableEmail(PDO $pdo, ?string $email, ?int $exceptUserId = null): ?string
    {
        $email = trim((string) $email);
        if ($email === '') return null;
        $sql = 'SELECT 1 FROM users WHERE email=?' . ($exceptUserId === null ? '' : ' AND id<>?') . ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $args = [$email];
        if ($exceptUserId !== null) $args[] = $exceptUserId;
        $stmt->execute($args);
        return $stmt->fetchColumn() ? null : $email;
    }

    private static function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $characters = str_split('A7!');
        for ($i = 0; $i < 9; $i++) $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }
        return implode('', $characters);
    }

    private static function assertProfileTable(string $table): void
    {
        if (!in_array($table, self::PROFILE_TABLES, true)) throw new RuntimeException('Unsupported profile type.');
    }
}
