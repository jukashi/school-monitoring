<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap/app.php';

use App\Controllers\DashboardController;
use App\Controllers\ReportController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;
use App\Core\AccountProvisioner;
use App\Core\Authorization;
use App\Core\Database;

function verify(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo=Database::connection();
$pdo->beginTransaction();

try {
    $pdo->prepare('INSERT INTO users(username,password_hash,display_name,status,password_changed_at) VALUES(?,?,?,"active",NOW())')
        ->execute(['registrar_access_test',password_hash('RegistrarTest!23',PASSWORD_DEFAULT),'Registrar Access Test']);
    $testUserId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_roles(user_id,role_id,assigned_by) SELECT ?,id,? FROM roles WHERE name="Registrar"')
        ->execute([$testUserId,$testUserId]);
    $_SESSION['user_id']=$testUserId;
    Authorization::forget();

    verify(Authorization::isRegistrar(),'Registrar role was not detected.');
    foreach (['students.view','students.create','students.edit','students.import','enrollments.manage','student_accounts.reset_password','teachers.view','attendance.view','reports.export'] as $permission) {
        verify(Authorization::allows($permission),"Missing Registrar permission: {$permission}");
    }
    foreach (['teachers.edit','attendance.record','tuition.view','insurance.view','inventory.view','users.view','roles.view','settings.view','audit.view','backups.view'] as $permission) {
        verify(!Authorization::allows($permission),"Registrar unexpectedly received: {$permission}");
    }

    $studentId=(int)$pdo->query('SELECT id FROM students WHERE user_id IS NOT NULL ORDER BY id LIMIT 1')->fetchColumn();
    $studentUserId=(int)$pdo->query("SELECT user_id FROM students WHERE id={$studentId}")->fetchColumn();
    verify($studentId>0&&Authorization::canViewStudent($studentId),'Registrar cannot view a student record.');
    verify(Authorization::canEditStudent($studentId),'Registrar cannot edit a student record.');
    verify(Authorization::canResetStudentPassword($studentId),'Registrar cannot reset a linked student password.');
    $teacherId=(int)$pdo->query('SELECT id FROM teachers ORDER BY id LIMIT 1')->fetchColumn();
    verify(!Authorization::canEditTeacher($teacherId),'Registrar can edit a teacher profile.');

    ob_start();(new StudentController())->show((string)$studentId);$studentHtml=(string)ob_get_clean();
    verify(!str_contains($studentHtml,'Temporary password'),'Student profile exposes password reset action.');
    verify(!str_contains($studentHtml,'Assigned supplies'),'Registrar student page exposed inventory.');
    verify((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_id={$testUserId} AND action='students.profile_viewed'")->fetchColumn()===1,'Registrar profile view was not audited.');

    ob_start();(new TeacherController())->show((string)$teacherId);$teacherHtml=(string)ob_get_clean();
    verify(str_contains($teacherHtml,'Assignment reference')&&!str_contains($teacherHtml,'<dt>Address</dt>'),'Registrar teacher view exposed private profile fields.');
    verify((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_id={$testUserId} AND action='teachers.profile_viewed'")->fetchColumn()===1,'Registrar teacher view was not audited.');

    $beforeHash=(string)$pdo->query("SELECT password_hash FROM users WHERE id={$studentUserId}")->fetchColumn();
    $credentials=AccountProvisioner::resetTemporaryPassword($pdo,$studentUserId);
    $afterHash=(string)$pdo->query("SELECT password_hash FROM users WHERE id={$studentUserId}")->fetchColumn();
    verify($beforeHash!==$afterHash&&!empty($credentials['temporary_password']),'Temporary password generation failed.');

    $_GET=['type'=>'students'];http_response_code(200);ob_start();(new ReportController())->index();$reportHtml=(string)ob_get_clean();
    verify(str_contains($reportHtml,'Student directory'),'Registrar student report is unavailable.');
    verify(!str_contains($reportHtml,'Tuition fee balances'),'Registrar report picker exposed tuition.');

    $_GET=['type'=>'tuition'];http_response_code(200);ob_start();(new ReportController())->index();$deniedHtml=(string)ob_get_clean();
    verify(http_response_code()===403&&str_contains($deniedHtml,'Access denied'),'Direct tuition report access was not denied.');

    $_GET=[];http_response_code(200);ob_start();(new DashboardController())->index();$dashboardHtml=(string)ob_get_clean();
    verify(str_contains($dashboardHtml,'Registrar workspace')&&str_contains($dashboardHtml,'Needs enrollment'),'Registrar dashboard was not rendered.');
    verify(!str_contains($dashboardHtml,'Tuition fees'),'Registrar navigation exposed tuition.');

    echo "Registrar access test passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
