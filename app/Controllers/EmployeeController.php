<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AccountProvisioner;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;
use Throwable;

final class EmployeeController
{
    public function index():void
    {
        $q=trim($_GET['q']??'');$sql='SELECT e.*,d.name department,(SELECT COUNT(*) FROM insurance_policies p WHERE p.employee_id=e.id) policy_count FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE 1=1';$args=[];
        if($q!==''){$sql.=' AND (e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.job_title LIKE ?)';$like='%'.$q.'%';$args=[$like,$like,$like,$like];}
        $sql.=' ORDER BY e.last_name,e.first_name LIMIT 250';$s=Database::connection()->prepare($sql);$s->execute($args);View::render('insurance/employees/index',['employees'=>$s->fetchAll(),'q'=>$q]);
    }
    public function create():void { View::render('insurance/employees/form',$this->data(null,[],[])); }
    public function edit(string $id):void { $employee=$this->find((int)$id);if($employee)View::render('insurance/employees/form',$this->data($employee,[],[])); }
    public function store():void { $this->save(); }
    public function update(string $id):void { $this->save((int)$id); }

    private function save(?int $id=null):void
    {
        $creating=$id===null;
        $in=['employee_no'=>trim($_POST['employee_no']??''),'department_id'=>(int)($_POST['department_id']??0),'first_name'=>trim($_POST['first_name']??''),'middle_name'=>trim($_POST['middle_name']??''),'last_name'=>trim($_POST['last_name']??''),'job_title'=>trim($_POST['job_title']??''),'email'=>trim($_POST['email']??''),'phone'=>trim($_POST['phone']??''),'employment_status'=>trim($_POST['employment_status']??'active')];
        $errors=array_merge(Validator::required($in,['employee_no'=>'Employee number','first_name'=>'First name','last_name'=>'Last name']),Validator::email($in['email']),Validator::phone($in['phone']));
        if(!in_array($in['employment_status'],['active','on_leave','inactive','separated'],true))$errors['status']='Invalid employment status.';
        if($errors){View::render('insurance/employees/form',$this->data($id?$this->raw($id):null,$errors,$in));return;}
        $pdo=Database::connection();$credentials=null;
        try{
            $pdo->beginTransaction();
            $v=[$in['employee_no'],$in['department_id']?:null,$in['first_name'],$in['middle_name']?:null,$in['last_name'],$in['job_title']?:null,$in['email']?:null,$in['phone']?:null,$in['employment_status']];
            if($id){$v[]=$id;$pdo->prepare('UPDATE employees SET employee_no=?,department_id=?,first_name=?,middle_name=?,last_name=?,job_title=?,email=?,phone=?,employment_status=? WHERE id=?')->execute($v);AccountProvisioner::syncLinkedAccount($pdo,'employees',$id,$in['employee_no'],$in['email'],$in['first_name'].' '.$in['last_name']);}
            else{$pdo->prepare('INSERT INTO employees(employee_no,department_id,first_name,middle_name,last_name,job_title,email,phone,employment_status) VALUES(?,?,?,?,?,?,?,?,?)')->execute($v);$id=(int)$pdo->lastInsertId();$credentials=AccountProvisioner::createForProfile($pdo,'employees',$id,$in['employee_no'],$in['email'],$in['first_name'].' '.$in['last_name'],'Viewer / Staff');}
            $pdo->commit();
            Auth::audit($creating?'employees.created':'employees.updated','employees',(string)$id,null,$in);flash('success','Employee profile saved.'.($creating?' A login account was created automatically.':''));if($credentials)flash('account_credentials',json_encode($credentials));Auth::redirect('/insurance/employees');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors['form']='The employee number is already used by a profile or login account.';View::render('insurance/employees/form',$this->data($id?$this->raw($id):null,$errors,$in));}
    }

    private function data(?array $employee,array $errors,array $input):array{return compact('employee','errors','input')+['departments'=>Database::connection()->query('SELECT * FROM departments WHERE status="active" ORDER BY name')->fetchAll()];}
    private function raw(int $id):?array{$s=Database::connection()->prepare('SELECT * FROM employees WHERE id=?');$s->execute([$id]);return$s->fetch()?:null;}
    private function find(int $id):?array{$e=$this->raw($id);if(!$e){http_response_code(404);View::render('errors/message',['title'=>'Employee not found','message'=>'The requested employee profile does not exist.']);return null;}return$e;}
}
