<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;
use Throwable;

final class PersonRecordDeletionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function deleteStudent(int $studentId): array
    {
        return $this->delete('students', $studentId, function () use ($studentId): void {
            $this->deleteInventoryIssues('student_id', $studentId);
            $this->deleteInsurancePolicies('student_id', $studentId);
            $this->execute('DELETE tp FROM tuition_payments tp JOIN tuition_assessments ta ON ta.id=tp.tuition_assessment_id WHERE ta.student_id=?', [$studentId]);
            $this->execute('DELETE FROM tuition_assessments WHERE student_id=?', [$studentId]);
            $this->execute('DELETE FROM student_attendance WHERE student_id=?', [$studentId]);
            $this->execute('DELETE FROM enrollments WHERE student_id=?', [$studentId]);
            $this->execute('DELETE FROM event_participants WHERE student_id=?', [$studentId]);
        });
    }

    public function deleteTeacher(int $teacherId): array
    {
        return $this->delete('teachers', $teacherId, function () use ($teacherId): void {
            $this->deleteInventoryIssues('teacher_id', $teacherId);
            $this->deleteInsurancePolicies('teacher_id', $teacherId);
            $this->execute('DELETE FROM teacher_attendance WHERE teacher_id=?', [$teacherId]);
            $this->execute('DELETE FROM teacher_assignments WHERE teacher_id=?', [$teacherId]);
            $this->execute('DELETE FROM event_participants WHERE teacher_id=?', [$teacherId]);
        });
    }

    public function deleteEmployee(int $employeeId): array
    {
        return $this->delete('employees', $employeeId, function () use ($employeeId): void {
            $this->deleteInventoryIssues('employee_id', $employeeId);
            $this->deleteInsurancePolicies('employee_id', $employeeId);
        });
    }

    private function delete(string $table, int $id, callable $deleteRelations): array
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare("SELECT * FROM {$table} WHERE id=? FOR UPDATE");
            $statement->execute([$id]);
            $record = $statement->fetch();
            if (!$record) {
                throw new RuntimeException('The requested record does not exist.');
            }

            $deleteRelations();
            $this->execute("DELETE FROM {$table} WHERE id=?", [$id]);
            $this->disableLinkedProfileAccount((int) ($record['user_id'] ?? 0));
            Auth::audit("{$table}.deleted", $table, (string) $id, $record);
            $this->pdo->commit();

            return $record;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function deleteInsurancePolicies(string $relationColumn, int $id): void
    {
        $this->execute("DELETE c FROM insurance_claims c JOIN insurance_policies p ON p.id=c.insurance_policy_id WHERE p.{$relationColumn}=?", [$id]);
        $this->execute("DELETE FROM insurance_policies WHERE {$relationColumn}=?", [$id]);
    }

    private function deleteInventoryIssues(string $relationColumn, int $id): void
    {
        $statement = $this->pdo->prepare("SELECT inventory_item_id, SUM(quantity-returned_quantity) outstanding FROM inventory_issues WHERE {$relationColumn}=? GROUP BY inventory_item_id");
        $statement->execute([$id]);
        $restoreStock = $this->pdo->prepare('UPDATE inventory_items SET quantity_on_hand=quantity_on_hand+? WHERE id=?');
        foreach ($statement->fetchAll() as $item) {
            $restoreStock->execute([(int) $item['outstanding'], (int) $item['inventory_item_id']]);
        }

        $this->execute("DELETE m FROM inventory_movements m JOIN inventory_issues i ON i.id=m.inventory_issue_id WHERE i.{$relationColumn}=?", [$id]);
        $this->execute("DELETE FROM inventory_issues WHERE {$relationColumn}=?", [$id]);
    }

    private function disableLinkedProfileAccount(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        $statement = $this->pdo->prepare('SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.name IN ("Super Administrator","Administrator","Administrator / Registrar") LIMIT 1');
        $statement->execute([$userId]);
        if (!$statement->fetchColumn()) {
            $this->execute('UPDATE users SET status="inactive" WHERE id=?', [$userId]);
        }
    }

    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }
}
