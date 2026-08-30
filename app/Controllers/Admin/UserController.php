<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AccountProvisioner;
use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\View;
use Throwable;

final class UserController
{
    public function index(): void
    {
        $users = Database::connection()->query(
            'SELECT u.id,u.username,u.email,u.display_name,u.status,u.last_login_at,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") roles,
                    MAX(s.student_no) student_no, MAX(t.employee_no) teacher_no
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id=u.id
             LEFT JOIN roles r ON r.id=ur.role_id
             LEFT JOIN students s ON s.user_id=u.id
             LEFT JOIN teachers t ON t.user_id=u.id
             GROUP BY u.id ORDER BY u.display_name'
        )->fetchAll();
        View::render('admin/users/index', compact('users'));
    }

    public function create(): void { View::render('admin/users/form', $this->data(null, [], [], [])); }

    public function edit(string $id): void
    {
        $user = $this->raw((int) $id);
        if (!$user) { $this->missing(); return; }
        View::render('admin/users/form', $this->data($user, [], [], []));
    }

    public function store(): void { $this->save(); }
    public function update(string $id): void { $this->save((int) $id); }

    public function temporaryPassword(string $id): void
    {
        $userId = (int) $id;
        $user = $this->raw($userId);
        if (!$user) { $this->missing(); return; }
        if ($userId === (int) Auth::user()['id']) {
            flash('error', 'Use your account settings to change your own password.');
            Auth::redirect('/admin/users');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $credentials = AccountProvisioner::resetTemporaryPassword($pdo, $userId);
            $pdo->commit();
            flash('account_credentials', json_encode($credentials));
            flash('success', 'A new temporary password was generated. The user must replace it at the next login.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'The temporary password could not be generated.');
        }
        Auth::redirect('/admin/users');
    }

    private function save(?int $id = null): void
    {
        $creating = $id === null;
        $input = [
            'username' => trim($_POST['username'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''), 'status' => trim($_POST['status'] ?? 'active'),
            'student_profile_id' => (int) ($_POST['student_profile_id'] ?? 0),
            'teacher_profile_id' => (int) ($_POST['teacher_profile_id'] ?? 0),
            'employee_profile_id' => (int) ($_POST['employee_profile_id'] ?? ($id ? ($this->raw($id)['employee_profile_id'] ?? 0) : 0)),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $roles = array_values(array_unique(array_filter(array_map('intval', $_POST['roles'] ?? []))));
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $input['username'])) $errors['username'] = 'Username must be 3-50 valid characters.';
        if ($input['display_name'] === '') $errors['display_name'] = 'Display name is required.';
        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email is invalid.';
        if (!in_array($input['status'], ['active','inactive','locked'], true)) $errors['status'] = 'Invalid account status.';
        if ($creating && strlen($password) < 10) $errors['password'] = 'A password of at least 10 characters is required.';
        if (!$creating && $password !== '' && strlen($password) < 10) $errors['password'] = 'New password must contain at least 10 characters.';
        if (!$roles) $errors['roles'] = 'Assign at least one role.';
        if ($id === (int) Auth::user()['id'] && $input['status'] !== 'active') $errors['status'] = 'You cannot disable your own active session.';
        if ($errors) { View::render('admin/users/form', $this->data($id ? $this->raw($id) : null, $errors, $input, $roles)); return; }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($creating) {
                $pdo->prepare('INSERT INTO users(username,email,password_hash,display_name,status,password_changed_at) VALUES(?,?,?,?,?,NOW())')
                    ->execute([$input['username'],$input['email'] ?: null,password_hash($password,PASSWORD_DEFAULT),$input['display_name'],$input['status']]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE users SET username=?,email=?,display_name=?,status=? WHERE id=?')
                    ->execute([$input['username'],$input['email'] ?: null,$input['display_name'],$input['status'],$id]);
                if ($password !== '') $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),failed_login_attempts=0,locked_until=NULL WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            }
            $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
            $assign = $pdo->prepare('INSERT INTO user_roles(user_id,role_id,assigned_by) SELECT ?,id,? FROM roles WHERE id=?');
            foreach ($roles as $roleId) $assign->execute([$id,Auth::user()['id'],$roleId]);
            $this->linkProfile($pdo, 'students', $id, $input['student_profile_id']);
            $this->linkProfile($pdo, 'teachers', $id, $input['teacher_profile_id']);
            $this->linkProfile($pdo, 'employees', $id, $input['employee_profile_id']);
            $pdo->commit();
            Authorization::forget();
            Auth::audit($creating ? 'users.created' : 'users.updated', 'users', (string) $id, null, [
                'username'=>$input['username'], 'roles'=>$roles,
                'student_profile_id'=>$input['student_profile_id'] ?: null, 'teacher_profile_id'=>$input['teacher_profile_id'] ?: null,
                'employee_profile_id'=>$input['employee_profile_id'] ?: null,
            ]);
            flash('success', 'User account saved.');
            Auth::redirect('/admin/users');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['form'] = str_contains($e->getMessage(), 'already linked') ? $e->getMessage() : 'Username, email, or selected profile is already in use.';
            View::render('admin/users/form', $this->data($id ? $this->raw($id) : null, $errors, $input, $roles));
        }
    }

    private function linkProfile(\PDO $pdo, string $table, int $userId, int $profileId): void
    {
        if (!in_array($table, ['students','teachers','employees'], true)) throw new \InvalidArgumentException('Invalid profile type.');
        $pdo->prepare("UPDATE {$table} SET user_id=NULL WHERE user_id=?")->execute([$userId]);
        if (!$profileId) return;
        $stmt = $pdo->prepare("UPDATE {$table} SET user_id=? WHERE id=? AND (user_id IS NULL OR user_id=?)");
        $stmt->execute([$userId,$profileId,$userId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException(ucfirst(rtrim($table, 's')) . ' profile is already linked to another account.');
    }

    private function data(?array $user, array $errors, array $input, array $selected = []): array
    {
        $pdo = Database::connection();
        if ($user && !$selected) { $s=$pdo->prepare('SELECT role_id FROM user_roles WHERE user_id=?');$s->execute([$user['id']]);$selected=array_map('intval',array_column($s->fetchAll(),'role_id')); }
        if ($user) { $input['student_profile_id'] ??= (int)($user['student_profile_id']??0);$input['teacher_profile_id'] ??= (int)($user['teacher_profile_id']??0);$input['employee_profile_id'] ??= (int)($user['employee_profile_id']??0); }
        return compact('user','errors','input','selected') + [
            'roles'=>$pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll(),
            'studentProfiles'=>$pdo->query('SELECT s.id,s.student_no,s.first_name,s.last_name,s.user_id,u.username FROM students s LEFT JOIN users u ON u.id=s.user_id ORDER BY s.last_name,s.first_name')->fetchAll(),
            'teacherProfiles'=>$pdo->query('SELECT t.id,t.employee_no,t.first_name,t.last_name,t.user_id,u.username FROM teachers t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.last_name,t.first_name')->fetchAll(),
            'employeeProfiles'=>$pdo->query('SELECT e.id,e.employee_no,e.first_name,e.last_name,e.user_id,u.username FROM employees e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.last_name,e.first_name')->fetchAll(),
        ];
    }

    private function raw(int $id): ?array
    {
        $s=Database::connection()->prepare('SELECT u.*,(SELECT id FROM students WHERE user_id=u.id LIMIT 1) student_profile_id,(SELECT id FROM teachers WHERE user_id=u.id LIMIT 1) teacher_profile_id,(SELECT id FROM employees WHERE user_id=u.id LIMIT 1) employee_profile_id FROM users u WHERE u.id=?');
        $s->execute([$id]);return $s->fetch() ?: null;
    }

    private function missing(): void { http_response_code(404);View::render('errors/message',['title'=>'User not found','message'=>'The requested user account does not exist.']); }
}
