<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Authorization;
use App\Core\Database;
use App\Services\PersonRecordDeletionService;

function verifyDelete(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$administratorId = (int) $pdo->query('SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.name="Super Administrator" ORDER BY ur.user_id LIMIT 1')->fetchColumn();
verifyDelete($administratorId > 0, 'A Super Administrator account is required for this test.');
$_SESSION['user_id'] = $administratorId;
Authorization::forget();
verifyDelete(Authorization::isSuperAdministrator(), 'The Super Administrator role was not detected.');

$suffix = bin2hex(random_bytes(5));
$service = new PersonRecordDeletionService($pdo);
$ids = ['students' => 0, 'teachers' => 0, 'employees' => 0, 'inventory_items' => 0];

try {
    $pdo->prepare('INSERT INTO students(student_no,first_name,last_name) VALUES(?,?,?)')->execute(["DELETE-S-{$suffix}", 'Delete', 'Student']);
    $ids['students'] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO teachers(employee_no,first_name,last_name) VALUES(?,?,?)')->execute(["DELETE-T-{$suffix}", 'Delete', 'Teacher']);
    $ids['teachers'] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO employees(employee_no,first_name,last_name) VALUES(?,?,?)')->execute(["DELETE-E-{$suffix}", 'Delete', 'Employee']);
    $ids['employees'] = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO inventory_items(sku,item_type,name,quantity_on_hand,created_by) VALUES(?,"other","Deletion test item",5,?)')->execute(["DELETE-I-{$suffix}", $administratorId]);
    $ids['inventory_items'] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO inventory_issues(inventory_item_id,recipient_type,employee_id,quantity,returned_quantity,issued_on,issued_by) VALUES(?,"employee",?,2,0,CURDATE(),?)')->execute([$ids['inventory_items'], $ids['employees'], $administratorId]);
    $issueId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO inventory_movements(inventory_item_id,inventory_issue_id,movement_type,quantity,balance_after,occurred_on,recorded_by) VALUES(?,?,"issue",2,5,CURDATE(),?)')->execute([$ids['inventory_items'], $issueId, $administratorId]);
    $pdo->prepare('INSERT INTO insurance_policies(holder_type,employee_id,provider,policy_no,coverage_start,coverage_end,created_by) VALUES("employee",?,"Deletion test",?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),?)')->execute([$ids['employees'], "DELETE-P-{$suffix}", $administratorId]);
    $policyId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO insurance_claims(insurance_policy_id,claim_no,filed_on,claim_amount,recorded_by) VALUES(?,?,CURDATE(),1,?)')->execute([$policyId, "DELETE-C-{$suffix}", $administratorId]);

    $service->deleteStudent($ids['students']);
    $service->deleteTeacher($ids['teachers']);
    $service->deleteEmployee($ids['employees']);

    foreach (['students', 'teachers', 'employees'] as $table) {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=?");
        $statement->execute([$ids[$table]]);
        verifyDelete((int) $statement->fetchColumn() === 0, "The {$table} record was not deleted.");
    }
    verifyDelete((int) $pdo->query("SELECT COUNT(*) FROM inventory_issues WHERE id={$issueId}")->fetchColumn() === 0, 'The linked inventory issue was not deleted.');
    verifyDelete((int) $pdo->query("SELECT quantity_on_hand FROM inventory_items WHERE id={$ids['inventory_items']}")->fetchColumn() === 7, 'Outstanding inventory was not restored.');
    verifyDelete((int) $pdo->query("SELECT COUNT(*) FROM insurance_policies WHERE id={$policyId}")->fetchColumn() === 0, 'The linked policy was not deleted.');
    verifyDelete((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('students.deleted','teachers.deleted','employees.deleted') AND entity_id IN ('{$ids['students']}','{$ids['teachers']}','{$ids['employees']}')")->fetchColumn() === 3, 'Deletion audit entries were not created.');

    echo "Delete records test passed.\n";
} finally {
    $pdo->prepare("DELETE FROM audit_logs WHERE action IN ('students.deleted','teachers.deleted','employees.deleted') AND entity_id IN (?,?,?)")
        ->execute([(string) $ids['students'], (string) $ids['teachers'], (string) $ids['employees']]);
    foreach (['students', 'teachers', 'employees'] as $table) {
        if ($ids[$table] > 0) {
            $pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([$ids[$table]]);
        }
    }
    if ($ids['inventory_items'] > 0) {
        $pdo->prepare('DELETE FROM inventory_items WHERE id=?')->execute([$ids['inventory_items']]);
    }
}
