<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestedPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestedPath);
    if ($requestedPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Controllers\Admin\RoleController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\InstallerController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;
use App\Controllers\AttendanceController;
use App\Controllers\EventController;
use App\Controllers\ReportController;
use App\Controllers\TuitionController;
use App\Controllers\InsuranceController;
use App\Controllers\InventoryController;
use App\Controllers\BulkImportController;
use App\Controllers\EmployeeController;
use App\Controllers\Admin\AcademicController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\OperationsController;
use App\Controllers\Admin\BackupController;
use App\Core\Auth;
use App\Core\Router;

$router = new Router();
$installer = new InstallerController();
$auth = new AuthController();
$dashboard = new DashboardController();
$roles = new RoleController();
$academics = new AcademicController();
$students = new StudentController();
$teachers = new TeacherController();
$attendance = new AttendanceController();
$events = new EventController();
$reports = new ReportController();
$tuition = new TuitionController();
$insurance = new InsuranceController();
$inventory = new InventoryController();
$bulkImports = new BulkImportController();
$employees = new EmployeeController();
$users = new UserController();
$operations = new OperationsController();
$backups = new BackupController();

$router->get('/', static function (): void {
    Auth::redirect(Auth::check() ? '/dashboard' : '/login');
});
$router->get('/install', [$installer, 'show']);
$router->post('/install', [$installer, 'install']);
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/password/change', [$auth, 'showPasswordChange']);
$router->post('/password/change', [$auth, 'changePassword']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/dashboard', [$dashboard, 'index'], 'dashboard.view');
$router->get('/admin/roles', [$roles, 'index'], 'roles.view');
$router->get('/admin/roles/{id}/edit', [$roles, 'edit'], 'roles.manage');
$router->post('/admin/roles/{id}', [$roles, 'update'], 'roles.manage');
$router->get('/admin/academics', [$academics, 'index'], 'academics.view');
$router->post('/admin/academics/{type}', [$academics, 'store'], 'academics.manage');
$router->get('/students', [$students, 'index'], 'students.view');
$router->get('/students/create', [$students, 'create'], 'students.create');
$router->post('/students', [$students, 'store'], 'students.create');
$router->get('/students/import', [$bulkImports, 'students'], 'students.import');
$router->post('/students/import', [$bulkImports, 'importStudents'], 'students.import');
$router->get('/students/import/template', [$bulkImports, 'studentTemplate'], 'students.import');
$router->get('/students/{id}', [$students, 'show'], 'students.view');
$router->get('/students/{id}/edit', [$students, 'edit'], 'students.view');
$router->post('/students/{id}', [$students, 'update'], 'students.view');
$router->post('/students/{id}/guardians', [$students, 'addGuardian'], 'students.edit');
$router->post('/students/{id}/guardians/{guardianId}', [$students, 'updateGuardian'], 'students.edit');
$router->post('/students/{id}/temporary-password', [$students, 'temporaryPassword'], 'student_accounts.reset_password');
$router->get('/teachers', [$teachers, 'index'], 'teachers.view');
$router->get('/teachers/create', [$teachers, 'create'], 'teachers.create');
$router->post('/teachers', [$teachers, 'store'], 'teachers.create');
$router->get('/teachers/import', [$bulkImports, 'teachers'], 'teachers.create');
$router->post('/teachers/import', [$bulkImports, 'importTeachers'], 'teachers.create');
$router->get('/teachers/import/template', [$bulkImports, 'teacherTemplate'], 'teachers.create');
$router->get('/teachers/{id}', [$teachers, 'show'], 'teachers.view');
$router->get('/teachers/{id}/edit', [$teachers, 'edit'], 'teachers.view');
$router->post('/teachers/{id}', [$teachers, 'update'], 'teachers.view');
$router->get('/attendance/students', [$attendance, 'students'], 'attendance.view');
$router->post('/attendance/students', [$attendance, 'recordStudents'], 'attendance.record');
$router->get('/attendance/teachers', [$attendance, 'teachers'], 'attendance.view');
$router->post('/attendance/teachers', [$attendance, 'recordTeachers'], 'attendance.record');
$router->get('/events', [$events, 'index'], 'events.view');
$router->get('/events/create', [$events, 'create'], 'events.create');
$router->post('/events', [$events, 'store'], 'events.create');
$router->get('/events/{id}', [$events, 'show'], 'events.view');
$router->get('/events/{id}/edit', [$events, 'edit'], 'events.edit');
$router->post('/events/{id}', [$events, 'update'], 'events.edit');
$router->post('/events/{id}/participants', [$events, 'addParticipant'], 'events.manage_participants');
$router->post('/events/{id}/attendance', [$events, 'recordAttendance'], 'events.manage_participants');
$router->get('/reports', [$reports, 'index'], 'reports.view');
$router->get('/reports/export', [$reports, 'export'], 'reports.export');
$router->get('/tuition', [$tuition, 'index'], 'tuition.view');
$router->get('/tuition/create', [$tuition, 'create'], 'tuition.manage');
$router->post('/tuition', [$tuition, 'store'], 'tuition.manage');
$router->get('/tuition/payment', [$tuition, 'paymentForm'], 'tuition.record_payment');
$router->post('/tuition/payment', [$tuition, 'makePayment'], 'tuition.record_payment');
$router->get('/tuition/{id}/payments/{paymentId}/receipt', [$tuition, 'receipt'], 'tuition.view');
$router->get('/tuition/{id}', [$tuition, 'show'], 'tuition.view');
$router->post('/tuition/{id}/payments', [$tuition, 'recordPayment'], 'tuition.record_payment');
$router->post('/tuition/{id}/status', [$tuition, 'updateStatus'], 'tuition.manage');
$router->get('/insurance', [$insurance, 'index'], 'insurance.view');
$router->get('/insurance/create', [$insurance, 'create'], 'insurance.manage');
$router->post('/insurance', [$insurance, 'store'], 'insurance.manage');
$router->get('/insurance/employees', [$employees, 'index'], 'insurance.view');
$router->get('/insurance/employees/create', [$employees, 'create'], 'insurance.manage');
$router->post('/insurance/employees', [$employees, 'store'], 'insurance.manage');
$router->get('/insurance/employees/{id}/edit', [$employees, 'edit'], 'insurance.manage');
$router->post('/insurance/employees/{id}', [$employees, 'update'], 'insurance.manage');
$router->get('/insurance/{id}', [$insurance, 'show'], 'insurance.view');
$router->post('/insurance/{id}/claims', [$insurance, 'addClaim'], 'insurance.claims');
$router->post('/insurance/{id}/claims/{claimId}', [$insurance, 'updateClaim'], 'insurance.claims');
$router->post('/insurance/{id}/status', [$insurance, 'updateStatus'], 'insurance.manage');
$router->get('/uniform-id', [$inventory, 'index'], 'inventory.view');
$router->get('/uniform-id/create', [$inventory, 'create'], 'inventory.manage');
$router->post('/uniform-id', [$inventory, 'store'], 'inventory.manage');
$router->get('/uniform-id/issue', [$inventory, 'issueForm'], 'inventory.issue');
$router->post('/uniform-id/issue', [$inventory, 'issue'], 'inventory.issue');
$router->post('/uniform-id/{id}/adjust', [$inventory, 'adjust'], 'inventory.manage');
$router->post('/uniform-id/issues/{id}/return', [$inventory, 'returnIssue'], 'inventory.issue');
$router->get('/admin/users', [$users, 'index'], 'users.view');
$router->get('/admin/users/create', [$users, 'create'], 'users.create');
$router->post('/admin/users', [$users, 'store'], 'users.create');
$router->get('/admin/users/{id}/edit', [$users, 'edit'], 'users.edit');
$router->post('/admin/users/{id}', [$users, 'update'], 'users.edit');
$router->post('/admin/users/{id}/temporary-password', [$users, 'temporaryPassword'], 'users.edit');
$router->get('/admin/settings', [$operations, 'settings'], 'settings.view');
$router->post('/admin/settings', [$operations, 'updateSettings'], 'settings.manage');
$router->post('/admin/settings/logo', [$operations, 'uploadLogo'], 'settings.manage');
$router->post('/admin/settings/logo-position', [$operations, 'updateLogoPosition'], 'settings.manage');
$router->post('/admin/announcements', [$operations, 'addAnnouncement'], 'settings.manage');
$router->get('/admin/audit', [$operations, 'audit'], 'audit.view');
$router->get('/admin/backups', [$backups, 'index'], 'backups.view');
$router->post('/admin/backups', [$backups, 'create'], 'backups.create');
$router->get('/admin/backups/{id}/download', [$backups, 'download'], 'backups.view');
$router->post('/admin/backups/{id}/restore', [$backups, 'restore'], 'backups.restore');

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $error) {
    http_response_code(500);
    $logDirectory = APP_ROOT . '/storage/logs';
    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0775, true);
    }
    error_log('[' . date(DATE_ATOM) . '] ' . (string) $error . PHP_EOL, 3, $logDirectory . '/application.log');
    if (\App\Core\Env::bool('APP_DEBUG', false)) {
        echo '<pre>' . e((string) $error) . '</pre>';
    } else {
        \App\Core\View::render('errors/message', ['title' => 'Application error', 'message' => 'An unexpected error occurred. Check the application log or configuration.']);
    }
}
