<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Controllers\BulkImportController;
use App\Controllers\ReportController;
use App\Core\Database;
use App\Core\Validator;

function verifyTeacherIds(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$columns = $pdo->query("SELECT column_name,data_type,character_maximum_length,is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teachers' AND column_name IN ('sss_no','pagibig_no','philhealth_no') ORDER BY ordinal_position")->fetchAll();
verifyTeacherIds(count($columns) === 3, 'Teacher government ID columns are missing.');
foreach ($columns as $column) {
    verifyTeacherIds($column['data_type'] === 'varchar' && (int) $column['character_maximum_length'] === 30 && $column['is_nullable'] === 'YES', $column['column_name'] . ' must be a nullable VARCHAR(30).');
}

verifyTeacherIds(Validator::governmentId('', 'sss_no', 'SSS number') === [], 'Government IDs must be optional.');
verifyTeacherIds(Validator::governmentId('12-3456789-0', 'sss_no', 'SSS number') === [], 'Formatted government IDs should be valid.');
verifyTeacherIds(Validator::governmentId('invalid/id', 'sss_no', 'SSS number') !== [], 'Invalid government ID characters were accepted.');

$reflection = new ReflectionClass(BulkImportController::class);
$headers = $reflection->getReflectionConstant('TEACHER_HEADERS')->getValue();
foreach (['sss_no', 'pagibig_no', 'philhealth_no'] as $field) {
    verifyTeacherIds(in_array($field, $headers, true), "Teacher import template is missing {$field}.");
}

$reportMethod = new ReflectionMethod(ReportController::class, 'data');
$reportMethod->setAccessible(true);
[$reportColumns] = $reportMethod->invoke(new ReportController(), 'teachers', date('Y-m-01'), date('Y-m-d'));
foreach (['sss_no', 'pagibig_no', 'philhealth_no'] as $field) {
    verifyTeacherIds(isset($reportColumns[$field]), "Teacher export is missing {$field}.");
}

$teacher = null;
$input = [];
$errors = [];
$departments = [];
$selfEditing = false;
ob_start();
include dirname(__DIR__) . '/app/Views/teachers/form.php';
$form = (string) ob_get_clean();
foreach (['sss_no', 'pagibig_no', 'philhealth_no'] as $field) {
    verifyTeacherIds(str_contains($form, 'name="' . $field . '"'), "Teacher form is missing {$field}.");
    verifyTeacherIds(!preg_match('/<input[^>]*required[^>]*name="' . $field . '"/', $form), "{$field} must not be required.");
}

echo "Teacher government IDs test passed.\n";
