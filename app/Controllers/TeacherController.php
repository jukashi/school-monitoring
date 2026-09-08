<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AccountProvisioner;
use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;
use App\Services\PersonRecordDeletionService;
use Throwable;

final class TeacherController
{
    public function index(): void
    {
        if(Authorization::isTeacherOnly()){
            $teacherId=Authorization::ownTeacherId();
            if($teacherId!==null)Auth::redirect('/teachers/'.$teacherId);
            $this->deny('Your account is not linked to a teacher profile. Ask an administrator to link it before using My profile.');return;
        }
        $q=trim($_GET['q']??'');$status=trim($_GET['status']??'');$sql='SELECT t.*,d.name department FROM teachers t LEFT JOIN departments d ON d.id=t.department_id WHERE 1=1';$args=[];
        if($q!==''){$sql.=' AND (t.employee_no LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)';$like='%'.$q.'%';$args=[$like,$like,$like];}
        if($status!==''){$sql.=' AND t.employment_status=?';$args[]=$status;}$sql.=' ORDER BY t.last_name,t.first_name LIMIT 200';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($args);View::render('teachers/index',['teachers'=>$stmt->fetchAll(),'q'=>$q,'status'=>$status]);
    }

    public function create():void { if(!$this->canCreate())return;View::render('teachers/form',$this->data(null,[],[])); }
    public function edit(string $id):void { $teacherId=(int)$id;if(!Authorization::canEditTeacher($teacherId)){$this->deny();return;}$teacher=$this->find($teacherId);if($teacher)View::render('teachers/form',array_merge($this->data($teacher,[],[]),['selfEditing'=>Authorization::isTeacherOnly()])); }
    public function show(string $id):void
    {
        $teacherId=(int)$id;if(!Authorization::canViewTeacher($teacherId)){$this->deny();return;}$teacher=$this->find($teacherId);if(!$teacher)return;$pdo=Database::connection();$registrarView=Authorization::isRegistrar();
        $stmt=$pdo->prepare('(SELECT "subject" assignment_type,sub.code,sub.name,s.name section_name,g.name grade_level,sy.name school_year,ss.schedule_text,COALESCE(ss.room,s.room) room FROM teacher_assignments ta JOIN section_subjects ss ON ss.id=ta.section_subject_id JOIN subjects sub ON sub.id=ss.subject_id JOIN sections s ON s.id=ss.section_id JOIN grade_levels g ON g.id=s.grade_level_id JOIN school_years sy ON sy.id=s.school_year_id WHERE ta.teacher_id=?) UNION ALL (SELECT "adviser" assignment_type,"ADVISER" code,"Class adviser" name,s.name section_name,g.name grade_level,sy.name school_year,NULL schedule_text,s.room FROM sections s JOIN grade_levels g ON g.id=s.grade_level_id JOIN school_years sy ON sy.id=s.school_year_id WHERE s.adviser_teacher_id=?) ORDER BY school_year DESC,grade_level,section_name,assignment_type');$stmt->execute([$id,$id]);
        $inventoryIssues=[];if(Authorization::allows('inventory.view')){$inventory=$pdo->prepare('SELECT x.*,i.sku,i.name item_name,i.item_type,i.variant,i.unit,u.display_name issued_by_name FROM inventory_issues x JOIN inventory_items i ON i.id=x.inventory_item_id JOIN users u ON u.id=x.issued_by WHERE x.teacher_id=? ORDER BY x.issued_on DESC,x.id DESC');$inventory->execute([$id]);$inventoryIssues=$inventory->fetchAll();}
        if($registrarView)Auth::audit('teachers.profile_viewed','teachers',(string)$teacherId,null,['access_scope'=>'registrar_assignment_reference']);
        View::render('teachers/show',['teacher'=>$teacher,'assignments'=>$stmt->fetchAll(),'inventoryIssues'=>$inventoryIssues,'registrarView'=>$registrarView]);
    }

    public function store():void { if(!$this->canCreate())return;$this->save(); }
    public function update(string $id):void { $teacherId=(int)$id;if(!Authorization::canEditTeacher($teacherId)){$this->deny();return;}$this->save($teacherId); }

    public function destroy(string $id): void
    {
        if (!Authorization::isSuperAdministrator()) {
            $this->deny('Only a Super Administrator can delete teacher records.');
            return;
        }

        try {
            (new PersonRecordDeletionService(Database::connection()))->deleteTeacher((int) $id);
            flash('success', 'Teacher record deleted.');
        } catch (Throwable $exception) {
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'The teacher record could not be deleted.');
        }
        Auth::redirect('/teachers');
    }

    private function save(?int $id=null):void
    {
        $creating=$id===null;$selfEditing=!$creating&&Authorization::isTeacherOnly();$existing=$id?$this->raw($id):null;if($id&&!$existing){$this->find($id);return;}$in=$this->input();
        if($selfEditing&&$existing){foreach(['employee_no','department_id','hire_date','employment_status'] as $field)$in[$field]=$existing[$field]??'';}
        $errors=array_merge(Validator::required($in,['employee_no'=>'Employee number','first_name'=>'First name','last_name'=>'Last name']),Validator::email($in['email']),Validator::phone($in['phone']),Validator::date($in['birth_date'],'birth_date','Birth date'),Validator::date($in['hire_date'],'hire_date','Hire date'),Validator::governmentId($in['sss_no'],'sss_no','SSS number'),Validator::governmentId($in['pagibig_no'],'pagibig_no','Pag-IBIG number'),Validator::governmentId($in['philhealth_no'],'philhealth_no','PhilHealth number'));
        if($errors){View::render('teachers/form',array_merge($this->data($id?$this->raw($id):null,$errors,$in),['selfEditing'=>$selfEditing]));return;}
        $pdo=Database::connection();$credentials=null;
        try{
            $pdo->beginTransaction();
            $v=[$in['employee_no'],$in['sss_no']?:null,$in['pagibig_no']?:null,$in['philhealth_no']?:null,$in['department_id']?:null,$in['first_name'],$in['middle_name']?:null,$in['last_name'],$in['suffix']?:null,$in['sex']?:null,$in['birth_date']?:null,$in['phone']?:null,$in['email']?:null,$in['address']?:null,$in['hire_date']?:null,$in['employment_status']];
            if($id){$v[]=$id;$pdo->prepare('UPDATE teachers SET employee_no=?,sss_no=?,pagibig_no=?,philhealth_no=?,department_id=?,first_name=?,middle_name=?,last_name=?,suffix=?,sex=?,birth_date=?,phone=?,email=?,address=?,hire_date=?,employment_status=? WHERE id=?')->execute($v);AccountProvisioner::syncLinkedAccount($pdo,'teachers',$id,$in['employee_no'],$in['email'],$in['first_name'].' '.$in['last_name']);}
            else{$pdo->prepare('INSERT INTO teachers(employee_no,sss_no,pagibig_no,philhealth_no,department_id,first_name,middle_name,last_name,suffix,sex,birth_date,phone,email,address,hire_date,employment_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($v);$id=(int)$pdo->lastInsertId();$credentials=AccountProvisioner::createForProfile($pdo,'teachers',$id,$in['employee_no'],$in['email'],$in['first_name'].' '.$in['last_name'],'Teacher');}
            $pdo->commit();
            Auth::audit($creating?'teachers.created':'teachers.updated','teachers',(string)$id,null,$in);flash('success','Teacher profile saved.'.($creating?' A login account was created automatically.':''));if($credentials)flash('account_credentials',json_encode($credentials));Auth::redirect('/teachers/'.$id);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors['form']='The employee number is already used by a profile or login account.';View::render('teachers/form',array_merge($this->data($id?$this->raw($id):null,$errors,$in),['selfEditing'=>$selfEditing]));}
    }

    private function input():array { return ['employee_no'=>trim($_POST['employee_no']??''),'sss_no'=>trim($_POST['sss_no']??''),'pagibig_no'=>trim($_POST['pagibig_no']??''),'philhealth_no'=>trim($_POST['philhealth_no']??''),'department_id'=>trim($_POST['department_id']??''),'first_name'=>trim($_POST['first_name']??''),'middle_name'=>trim($_POST['middle_name']??''),'last_name'=>trim($_POST['last_name']??''),'suffix'=>trim($_POST['suffix']??''),'sex'=>trim($_POST['sex']??''),'birth_date'=>trim($_POST['birth_date']??''),'phone'=>trim($_POST['phone']??''),'email'=>trim($_POST['email']??''),'address'=>trim($_POST['address']??''),'hire_date'=>trim($_POST['hire_date']??''),'employment_status'=>trim($_POST['employment_status']??'active')]; }
    private function data(?array $teacher,array $errors,array $input):array { return compact('teacher','errors','input')+['selfEditing'=>false,'departments'=>Database::connection()->query('SELECT * FROM departments WHERE status="active" ORDER BY name')->fetchAll()]; }
    private function raw(int $id):?array { $s=Database::connection()->prepare('SELECT t.*,d.name department FROM teachers t LEFT JOIN departments d ON d.id=t.department_id WHERE t.id=?');$s->execute([$id]);return $s->fetch()?:null; }
    private function find(int $id):?array { $t=$this->raw($id);if(!$t){http_response_code(404);View::render('errors/message',['title'=>'Teacher not found','message'=>'The requested teacher profile does not exist.']);return null;}return $t; }
    private function canCreate():bool{if(Authorization::isStudentAdministrator()&&Authorization::allows('teachers.create'))return true;$this->deny();return false;}
    private function deny(string $message='You do not have permission to access this teacher profile.'):void{http_response_code(403);View::render('errors/message',['title'=>'Access denied','message'=>$message]);}
}
