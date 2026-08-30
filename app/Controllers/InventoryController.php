<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use PDO;
use Throwable;

final class InventoryController
{
    public function index(): void
    {
        $pdo = Database::connection();
        $q = trim($_GET['q'] ?? '');
        $type = trim($_GET['type'] ?? '');
        $sql = 'SELECT i.*,(SELECT COALESCE(SUM(x.quantity-x.returned_quantity),0) FROM inventory_issues x WHERE x.inventory_item_id=i.id AND x.status<>"returned") issued_quantity FROM inventory_items i WHERE 1=1';
        $args = [];
        if ($q !== '') {
            $sql .= ' AND (i.sku LIKE ? OR i.name LIKE ? OR i.variant LIKE ?)';
            $like = '%' . $q . '%';
            $args = [$like, $like, $like];
        }
        if (in_array($type, ['uniform', 'id_card', 'other'], true)) {
            $sql .= ' AND i.item_type=?';
            $args[] = $type;
        }
        $sql .= ' ORDER BY i.is_active DESC,i.item_type,i.name,i.variant LIMIT 300';
        $statement = $pdo->prepare($sql);
        $statement->execute($args);
        $items = $statement->fetchAll();
        $summary = $pdo->query('SELECT COUNT(*) item_count,COALESCE(SUM(quantity_on_hand),0) units_available,SUM(is_active=1 AND quantity_on_hand<=reorder_level) low_stock_count,(SELECT COALESCE(SUM(quantity-returned_quantity),0) FROM inventory_issues WHERE status<>"returned") issued_count FROM inventory_items')->fetch();
        $issues = $pdo->query('SELECT x.*,i.sku,i.name item_name,i.variant,COALESCE(CONCAT(s.last_name,", ",s.first_name),CONCAT(t.last_name,", ",t.first_name),CONCAT(e.last_name,", ",e.first_name)) recipient_name,COALESCE(s.student_no,t.employee_no,e.employee_no) reference_no FROM inventory_issues x JOIN inventory_items i ON i.id=x.inventory_item_id LEFT JOIN students s ON s.id=x.student_id LEFT JOIN teachers t ON t.id=x.teacher_id LEFT JOIN employees e ON e.id=x.employee_id ORDER BY x.issued_on DESC,x.id DESC LIMIT 100')->fetchAll();
        $movements = $pdo->query('SELECT m.*,i.sku,i.name item_name,u.display_name recorder FROM inventory_movements m JOIN inventory_items i ON i.id=m.inventory_item_id JOIN users u ON u.id=m.recorded_by ORDER BY m.created_at DESC,m.id DESC LIMIT 20')->fetchAll();
        View::render('inventory/index', compact('items', 'summary', 'issues', 'movements', 'q', 'type'));
    }

    public function create(): void
    {
        View::render('inventory/form', ['errors' => [], 'input' => []]);
    }

    public function store(): void
    {
        $input = [
            'sku' => strtoupper(trim($_POST['sku'] ?? '')), 'item_type' => trim($_POST['item_type'] ?? ''),
            'name' => trim($_POST['name'] ?? ''), 'variant' => trim($_POST['variant'] ?? ''),
            'unit' => trim($_POST['unit'] ?? 'piece'), 'opening_stock' => trim($_POST['opening_stock'] ?? '0'),
            'reorder_level' => trim($_POST['reorder_level'] ?? '0'), 'unit_cost' => trim($_POST['unit_cost'] ?? ''),
        ];
        $errors = [];
        if ($input['sku'] === '' || $input['name'] === '') $errors[] = 'SKU and item name are required.';
        if (!in_array($input['item_type'], ['uniform', 'id_card', 'other'], true)) $errors[] = 'Choose Uniform, ID card, or Other item.';
        foreach (['opening_stock', 'reorder_level'] as $field) if (filter_var($input[$field], FILTER_VALIDATE_INT) === false || (int)$input[$field] < 0) $errors[] = 'Stock quantities must be whole numbers of zero or more.';
        if ($input['unit_cost'] !== '' && (!is_numeric($input['unit_cost']) || (float)$input['unit_cost'] < 0)) $errors[] = 'Unit cost cannot be negative.';
        if ($errors) { View::render('inventory/form', compact('errors', 'input')); return; }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $opening = (int)$input['opening_stock'];
            $pdo->prepare('INSERT INTO inventory_items(sku,item_type,name,variant,unit,quantity_on_hand,reorder_level,unit_cost,created_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$input['sku'],$input['item_type'],$input['name'],$input['variant']?:null,$input['unit']?:'piece',$opening,(int)$input['reorder_level'],$input['unit_cost']!==''?$input['unit_cost']:null,Auth::user()['id']]);
            $id = (int)$pdo->lastInsertId();
            if ($opening > 0) $this->movement($pdo,$id,null,'opening',$opening,$opening,date('Y-m-d'),'Opening inventory balance');
            $pdo->commit();
            Auth::audit('inventory.item_created','inventory_items',(string)$id,null,$input);
            flash('success','Inventory item created.');
            Auth::redirect('/uniform-id');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e instanceof \PDOException ? 'The SKU is already in use or a value is invalid.' : $e->getMessage();
            View::render('inventory/form', compact('errors', 'input'));
        }
    }

    public function adjust(string $id): void
    {
        $quantity = (int)($_POST['quantity'] ?? 0);
        $mode = trim($_POST['movement_type'] ?? '');
        $date = trim($_POST['occurred_on'] ?? '');
        if ($quantity < 1 || !in_array($mode,['stock_in','adjustment_in','adjustment_out'],true) || !$this->validDate($date)) {
            flash('error','Choose an adjustment type, valid date, and quantity greater than zero.'); Auth::redirect('/uniform-id');
        }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $item = $this->lockItem($pdo,(int)$id);
            if (!$item) throw new \RuntimeException('Inventory item not found.');
            $subtract = $mode === 'adjustment_out';
            if ($subtract && $quantity > (int)$item['quantity_on_hand']) throw new \RuntimeException('Adjustment exceeds available stock.');
            $balance = (int)$item['quantity_on_hand'] + ($subtract ? -$quantity : $quantity);
            $pdo->prepare('UPDATE inventory_items SET quantity_on_hand=? WHERE id=?')->execute([$balance,(int)$id]);
            $this->movement($pdo,(int)$id,null,$mode,$quantity,$balance,$date,substr(trim($_POST['notes']??''),0,500)?:null);
            $pdo->commit();
            Auth::audit('inventory.stock_adjusted','inventory_items',$id,null,['movement_type'=>$mode,'quantity'=>$quantity,'balance_after'=>$balance]);
            flash('success','Stock balance updated.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error',$e->getMessage());
        }
        Auth::redirect('/uniform-id');
    }

    public function issueForm(): void
    {
        $pdo = Database::connection();
        View::render('inventory/issue', [
            'items'=>$pdo->query('SELECT * FROM inventory_items WHERE is_active=1 AND quantity_on_hand>0 ORDER BY item_type,name,variant')->fetchAll(),
            'students'=>$pdo->query('SELECT id,student_no,first_name,last_name FROM students WHERE student_status="active" ORDER BY last_name,first_name')->fetchAll(),
            'teachers'=>$pdo->query('SELECT id,employee_no,first_name,last_name FROM teachers WHERE employment_status="active" ORDER BY last_name,first_name')->fetchAll(),
            'employees'=>$pdo->query('SELECT id,employee_no,first_name,last_name FROM employees WHERE employment_status="active" ORDER BY last_name,first_name')->fetchAll(),
        ]);
    }

    public function issue(): void
    {
        $itemId=(int)($_POST['inventory_item_id']??0); $recipientType=trim($_POST['recipient_type']??''); $recipientId=(int)($_POST['recipient_id']??0); $quantity=(int)($_POST['quantity']??0); $date=trim($_POST['issued_on']??'');
        if (!$itemId || !$recipientId || $quantity<1 || !in_array($recipientType,['student','teacher','employee'],true) || !$this->validDate($date)) { flash('error','Select an item and recipient, then enter a valid date and quantity.'); Auth::redirect('/uniform-id/issue'); }
        $pdo=Database::connection();
        try {
            $pdo->beginTransaction();
            $table=['student'=>'students','teacher'=>'teachers','employee'=>'employees'][$recipientType];
            $check=$pdo->prepare("SELECT id FROM {$table} WHERE id=?"); $check->execute([$recipientId]);
            if (!$check->fetchColumn()) throw new \RuntimeException('The selected recipient does not exist.');
            $item=$this->lockItem($pdo,$itemId);
            if (!$item || !(int)$item['is_active']) throw new \RuntimeException('The selected item is not available.');
            if ($quantity>(int)$item['quantity_on_hand']) throw new \RuntimeException('Not enough stock is available for this issue.');
            $ids=['student_id'=>null,'teacher_id'=>null,'employee_id'=>null]; $ids[$recipientType.'_id']=$recipientId;
            $pdo->prepare('INSERT INTO inventory_issues(inventory_item_id,recipient_type,student_id,teacher_id,employee_id,quantity,issued_on,notes,issued_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$itemId,$recipientType,$ids['student_id'],$ids['teacher_id'],$ids['employee_id'],$quantity,$date,substr(trim($_POST['notes']??''),0,500)?:null,Auth::user()['id']]);
            $issueId=(int)$pdo->lastInsertId(); $balance=(int)$item['quantity_on_hand']-$quantity;
            $pdo->prepare('UPDATE inventory_items SET quantity_on_hand=? WHERE id=?')->execute([$balance,$itemId]);
            $this->movement($pdo,$itemId,$issueId,'issue',$quantity,$balance,$date,'Issued to '.$recipientType.' profile #'.$recipientId);
            $pdo->commit();
            Auth::audit('inventory.item_issued','inventory_issues',(string)$issueId,null,['item_id'=>$itemId,'recipient_type'=>$recipientType,'recipient_id'=>$recipientId,'quantity'=>$quantity]);
            flash('success','Item issued and inventory reduced automatically.'); Auth::redirect('/uniform-id');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack(); flash('error',$e->getMessage()); Auth::redirect('/uniform-id/issue');
        }
    }

    public function returnIssue(string $id): void
    {
        $quantity=(int)($_POST['quantity']??0); $date=trim($_POST['occurred_on']??'');
        if ($quantity<1 || !$this->validDate($date)) { flash('error','Return quantity and date are required.'); Auth::redirect('/uniform-id'); }
        $pdo=Database::connection();
        try {
            $pdo->beginTransaction();
            $s=$pdo->prepare('SELECT * FROM inventory_issues WHERE id=? FOR UPDATE'); $s->execute([(int)$id]); $issue=$s->fetch();
            if (!$issue) throw new \RuntimeException('Issue record not found.');
            $remaining=(int)$issue['quantity']-(int)$issue['returned_quantity'];
            if ($quantity>$remaining) throw new \RuntimeException('Return quantity exceeds the outstanding issued quantity.');
            $item=$this->lockItem($pdo,(int)$issue['inventory_item_id']);
            if (!$item) throw new \RuntimeException('Inventory item not found.');
            $returned=(int)$issue['returned_quantity']+$quantity; $status=$returned===(int)$issue['quantity']?'returned':'partially_returned'; $balance=(int)$item['quantity_on_hand']+$quantity;
            $pdo->prepare('UPDATE inventory_issues SET returned_quantity=?,status=? WHERE id=?')->execute([$returned,$status,(int)$id]);
            $pdo->prepare('UPDATE inventory_items SET quantity_on_hand=? WHERE id=?')->execute([$balance,(int)$item['id']]);
            $this->movement($pdo,(int)$item['id'],(int)$id,'return',$quantity,$balance,$date,substr(trim($_POST['notes']??''),0,500)?:'Returned issued stock');
            $pdo->commit();
            Auth::audit('inventory.item_returned','inventory_issues',$id,null,['quantity'=>$quantity,'status'=>$status,'balance_after'=>$balance]);
            flash('success','Return recorded and inventory restored automatically.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack(); flash('error',$e->getMessage());
        }
        Auth::redirect('/uniform-id');
    }

    private function movement(PDO $pdo,int $itemId,?int $issueId,string $type,int $quantity,int $balance,string $date,?string $notes): void
    {
        $pdo->prepare('INSERT INTO inventory_movements(inventory_item_id,inventory_issue_id,movement_type,quantity,balance_after,occurred_on,notes,recorded_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$itemId,$issueId,$type,$quantity,$balance,$date,$notes,Auth::user()['id']]);
    }

    private function lockItem(PDO $pdo,int $id): ?array
    {
        $s=$pdo->prepare('SELECT * FROM inventory_items WHERE id=? FOR UPDATE'); $s->execute([$id]); return $s->fetch()?:null;
    }

    private function validDate(string $value): bool
    {
        $date=DateTimeImmutable::createFromFormat('Y-m-d',$value); return $date&&$date->format('Y-m-d')===$value;
    }
}
