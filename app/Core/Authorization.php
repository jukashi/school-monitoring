<?php

declare(strict_types=1);

namespace App\Core;

final class Authorization
{
    private static ?array $cachedPermissions = null;
    private static ?array $cachedRoles = null;

    public static function allows(string $permission): bool
    {
        if (!Auth::check()) {
            return false;
        }
        $permissions = self::permissions();
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function permissions(): array
    {
        if (self::$cachedPermissions !== null) {
            return self::$cachedPermissions;
        }
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?'
        );
        $stmt->execute([Auth::user()['id']]);
        return self::$cachedPermissions = array_column($stmt->fetchAll(), 'code');
    }

    public static function roles(): array
    {
        if (self::$cachedRoles !== null) {
            return self::$cachedRoles;
        }
        if (!Auth::check()) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?'
        );
        $stmt->execute([Auth::user()['id']]);
        return self::$cachedRoles = array_column($stmt->fetchAll(), 'name');
    }

    public static function hasAnyRole(string ...$roles): bool
    {
        return (bool) array_intersect($roles, self::roles());
    }

    public static function isStudentOnly(): bool
    {
        return self::hasAnyRole('Student')
            && !self::hasAnyRole('Super Administrator', 'Administrator', 'Administrator / Registrar', 'Registrar', 'Teacher');
    }

    public static function isSystemAdministrator(): bool
    {
        return self::hasAnyRole('Super Administrator', 'Administrator', 'Administrator / Registrar');
    }

    public static function isSuperAdministrator(): bool
    {
        return self::hasAnyRole('Super Administrator');
    }

    public static function isRegistrar(): bool
    {
        return self::hasAnyRole('Registrar') && !self::isSystemAdministrator();
    }

    public static function isStudentAdministrator(): bool
    {
        return self::isSystemAdministrator() || self::isRegistrar();
    }

    public static function isTeacherOnly(): bool
    {
        return self::hasAnyRole('Teacher')
            && !self::hasAnyRole('Super Administrator', 'Administrator', 'Administrator / Registrar', 'Registrar');
    }

    public static function ownTeacherId(): ?int
    {
        if (!Auth::check()) return null;
        $stmt = Database::connection()->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
        $stmt->execute([Auth::user()['id']]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function canViewTeacher(int $teacherId): bool
    {
        if (self::isStudentAdministrator()) return true;
        if (self::isTeacherOnly()) return self::ownTeacherId() === $teacherId;
        return self::allows('teachers.view');
    }

    public static function canEditTeacher(int $teacherId): bool
    {
        if (self::isStudentAdministrator()) return self::allows('teachers.edit');
        return self::isTeacherOnly() && self::ownTeacherId() === $teacherId;
    }

    public static function ownStudentId(): ?int
    {
        if (!Auth::check()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
        $stmt->execute([Auth::user()['id']]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function canBrowseStudents(): bool
    {
        return self::isStudentAdministrator() || self::hasAnyRole('Teacher');
    }

    public static function canViewStudent(int $studentId): bool
    {
        if (self::isStudentAdministrator()) {
            return true;
        }
        if (self::isStudentOnly()) {
            return self::ownStudentId() === $studentId;
        }
        if (!self::hasAnyRole('Teacher')) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM enrollments e
             JOIN sections sec ON sec.id = e.section_id
             WHERE e.student_id = ? AND e.status = "enrolled"
               AND (
                    EXISTS (SELECT 1 FROM teachers adviser WHERE adviser.id=sec.adviser_teacher_id AND adviser.user_id=?)
                    OR EXISTS (
                        SELECT 1 FROM section_subjects ss
                        JOIN teacher_assignments ta ON ta.section_subject_id=ss.id
                        JOIN teachers assigned_teacher ON assigned_teacher.id=ta.teacher_id
                        WHERE ss.section_id=sec.id AND assigned_teacher.user_id=?
                    )
               )
             LIMIT 1'
        );
        $stmt->execute([$studentId, Auth::user()['id'], Auth::user()['id']]);
        return (bool) $stmt->fetchColumn();
    }

    public static function canEditStudent(int $studentId): bool
    {
        if (self::isStudentAdministrator()) {
            return self::allows('students.edit');
        }
        return self::isStudentOnly() && self::ownStudentId() === $studentId;
    }

    public static function canResetStudentPassword(int $studentId): bool
    {
        if (!self::allows('student_accounts.reset_password')) return false;
        $stmt = Database::connection()->prepare('SELECT 1 FROM students WHERE id=? AND user_id IS NOT NULL LIMIT 1');
        $stmt->execute([$studentId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function forget(): void
    {
        self::$cachedPermissions = null;
        self::$cachedRoles = null;
    }
}
