<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AccountProvisioner;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;

final class StudentController
{
    public function index(): void
    {
        if (Authorization::isStudentOnly()) {
            $studentId = Authorization::ownStudentId();
            if ($studentId !== null) {
                Auth::redirect('/students/' . $studentId);
            }
            $this->deny('Your account is not linked to a student profile. Ask an administrator to link it before using My profile.');
            return;
        }
        if (!Authorization::canBrowseStudents()) {
            $this->deny();
            return;
        }
        $q=trim($_GET['q']??''); $status=trim($_GET['status']??'');
        $teacherView=Authorization::isTeacherOnly();
        $subjectSelect=$teacherView?',(SELECT GROUP_CONCAT(DISTINCT CONCAT(sub.code," — ",sub.name) ORDER BY sub.name SEPARATOR "||") FROM section_subjects tss JOIN teacher_assignments tta ON tta.section_subject_id=tss.id JOIN teachers tt ON tt.id=tta.teacher_id JOIN subjects sub ON sub.id=tss.subject_id WHERE tss.section_id=sec.id AND tt.user_id=?) assigned_subjects,EXISTS(SELECT 1 FROM teachers adv WHERE adv.id=sec.adviser_teacher_id AND adv.user_id=?) is_section_adviser':' ,NULL assigned_subjects,0 is_section_adviser';
        $sql='SELECT s.*,g.name grade_level,sec.name section_name,sy.name school_year'.$subjectSelect.' FROM students s LEFT JOIN enrollments e ON e.student_id=s.id AND e.status="enrolled" LEFT JOIN sections sec ON sec.id=e.section_id LEFT JOIN grade_levels g ON g.id=sec.grade_level_id LEFT JOIN school_years sy ON sy.id=e.school_year_id WHERE 1=1'; $args=[];
        if($teacherView){$args[] = Auth::user()['id'];$args[] = Auth::user()['id'];}
        if (!Authorization::isStudentAdministrator()) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM enrollments ae
                JOIN sections asec ON asec.id=ae.section_id
                WHERE ae.student_id=s.id AND ae.status="enrolled"
                  AND (
                    EXISTS (SELECT 1 FROM teachers adviser WHERE adviser.id=asec.adviser_teacher_id AND adviser.user_id=?)
                    OR EXISTS (
                        SELECT 1 FROM section_subjects ass
                        JOIN teacher_assignments ata ON ata.section_subject_id=ass.id
                        JOIN teachers assigned_teacher ON assigned_teacher.id=ata.teacher_id
                        WHERE ass.section_id=asec.id AND assigned_teacher.user_id=?
                    )
                  )
            )';
            $args[] = Auth::user()['id'];
            $args[] = Auth::user()['id'];
        }
        if($q!==''){$sql.=' AND (s.student_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';$like='%'.$q.'%';array_push($args,$like,$like,$like);}
        if($status!==''){$sql.=' AND s.student_status=?';$args[]=$status;}$sql.=' ORDER BY s.last_name,s.first_name LIMIT 200';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($args);
        View::render('students/index',['students'=>$stmt->fetchAll(),'q'=>$q,'status'=>$status,'teacherView'=>$teacherView]);
    }

    public function create(): void { if(!$this->canCreate())return; View::render('students/form',$this->formData(null,[],[])); }
    public function edit(string $id): void { $studentId=(int)$id;if(!Authorization::canEditStudent($studentId)){$this->deny();return;}$student=$this->find($studentId); if(!$student)return; View::render('students/form',array_merge($this->formData($student,[],[]),['selfEditing'=>Authorization::isStudentOnly()])); }
    public function show(string $id): void
    {
        $studentId=(int)$id;if(!Authorization::canViewStudent($studentId)){$this->deny();return;}$student=$this->find($studentId);if(!$student)return;$pdo=Database::connection();$teacherView=Authorization::isTeacherOnly();
        $enrollmentSql='SELECT e.*,sy.name school_year,g.name grade_level,s.name section_name FROM enrollments e JOIN school_years sy ON sy.id=e.school_year_id JOIN sections s ON s.id=e.section_id JOIN grade_levels g ON g.id=s.grade_level_id WHERE e.student_id=?'.($teacherView?' AND e.status="enrolled"':'').' ORDER BY sy.starts_on DESC';
        $stmt=$pdo->prepare($enrollmentSql);$stmt->execute([$id]);
        if($teacherView){$guard=$pdo->prepare('SELECT g.first_name,g.last_name,g.phone,sg.relationship,sg.is_primary FROM guardians g JOIN student_guardians sg ON sg.guardian_id=g.id WHERE sg.student_id=? AND sg.is_primary=1 ORDER BY g.last_name');}
        else{$guard=$pdo->prepare('SELECT g.*,sg.relationship,sg.is_primary FROM guardians g JOIN student_guardians sg ON sg.guardian_id=g.id WHERE sg.student_id=? ORDER BY sg.is_primary DESC,g.last_name');}
        $guard->execute([$id]);
        $inventoryIssues=[];$teacherSubjects=[];
        if($teacherView){
            $subjects=$pdo->prepare('SELECT DISTINCT sub.code,sub.name FROM enrollments e JOIN section_subjects ss ON ss.section_id=e.section_id JOIN teacher_assignments ta ON ta.section_subject_id=ss.id JOIN teachers t ON t.id=ta.teacher_id JOIN subjects sub ON sub.id=ss.subject_id WHERE e.student_id=? AND e.status="enrolled" AND t.user_id=? ORDER BY sub.name');
            $subjects->execute([$studentId,Auth::user()['id']]);$teacherSubjects=$subjects->fetchAll();
            Auth::audit('students.profile_viewed','students',(string)$studentId,null,['access_scope'=>'teacher_assigned']);
        }elseif(Authorization::allows('inventory.view')){
            $inventory=$pdo->prepare('SELECT x.*,i.sku,i.name item_name,i.item_type,i.variant,i.unit,u.display_name issued_by_name FROM inventory_issues x JOIN inventory_items i ON i.id=x.inventory_item_id JOIN users u ON u.id=x.issued_by WHERE x.student_id=? ORDER BY x.issued_on DESC,x.id DESC');$inventory->execute([$id]);$inventoryIssues=$inventory->fetchAll();
        }
        if(Authorization::isRegistrar())Auth::audit('students.profile_viewed','students',(string)$studentId,null,['access_scope'=>'registrar']);
        View::render('students/show',['student'=>$student,'enrollments'=>$stmt->fetchAll(),'guardians'=>$guard->fetchAll(),'inventoryIssues'=>$inventoryIssues,'teacherView'=>$teacherView,'teacherSubjects'=>$teacherSubjects]);
    }
    public function store(): void { if(!$this->canCreate())return;$this->save(); }
    public function update(string $id): void { $studentId=(int)$id;if(!Authorization::canEditStudent($studentId)){$this->deny();return;}$this->save($studentId); }
    public function addGuardian(string $id): void
    {
        if (!Authorization::isStudentAdministrator() || !Authorization::allows('students.edit')) {$this->deny();return;}
        $student=$this->find((int)$id);if(!$student)return;
        $first=trim($_POST['first_name']??'');$last=trim($_POST['last_name']??'');$relationship=trim($_POST['relationship']??'');
        if($first===''||$last===''||$relationship===''){flash('error','Guardian first name, last name, and relationship are required.');Auth::redirect('/students/'.$id);}
        $email=trim($_POST['email']??'');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('error','Guardian email address is invalid.');Auth::redirect('/students/'.$id);}$phone=trim($_POST['phone']??'');if(Validator::phone($phone)){flash('error','Guardian phone number must contain exactly 11 digits.');Auth::redirect('/students/'.$id);}
        $guardianFieldErrors=$this->validateGuardianFields($first,$last,$relationship,$email,$phone,trim($_POST['address']??''),trim($_POST['occupation']??''));
        if($guardianFieldErrors!==[]){flash('error',$guardianFieldErrors[0]);Auth::redirect('/students/'.$id);}
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$pdo->prepare('INSERT INTO guardians(first_name,last_name,phone,email,address,occupation) VALUES(?,?,?,?,?,?)')->execute([$first,$last,$phone?:null,$email?:null,trim($_POST['address']??'')?:null,trim($_POST['occupation']??'')?:null]);$guardianId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO student_guardians(student_id,guardian_id,relationship,is_primary,can_pick_up) VALUES(?,?,?,?,?)')->execute([(int)$id,$guardianId,$relationship,!empty($_POST['is_primary'])?1:0,!empty($_POST['can_pick_up'])?1:0]);$pdo->commit();Auth::audit('students.guardian_added','students',$id,null,['guardian_id'=>$guardianId]);flash('success','Guardian added to the student profile.');}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','The guardian could not be saved.');}
        Auth::redirect('/students/'.$id);
    }

    public function updateGuardian(string $id, string $guardianId): void
    {
        if (!Authorization::isStudentAdministrator() || !Authorization::allows('students.edit')) {$this->deny();return;}
        $studentId=(int)$id;$guardianRecordId=(int)$guardianId;
        $student=$this->find($studentId);if(!$student)return;
        $pdo=Database::connection();
        $link=$pdo->prepare('SELECT 1 FROM student_guardians WHERE student_id=? AND guardian_id=? LIMIT 1');
        $link->execute([$studentId,$guardianRecordId]);
        if(!$link->fetch()){$this->deny('The selected guardian is not linked to this student profile.');return;}

        $first=trim($_POST['first_name']??'');$last=trim($_POST['last_name']??'');$relationship=trim($_POST['relationship']??'');
        if($first===''||$last===''||$relationship===''){flash('error','Guardian first name, last name, and relationship are required.');Auth::redirect('/students/'.$studentId.'?edit_guardian='.$guardianRecordId);}
        $email=trim($_POST['email']??'');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('error','Guardian email address is invalid.');Auth::redirect('/students/'.$studentId.'?edit_guardian='.$guardianRecordId);}$phone=trim($_POST['phone']??'');if(Validator::phone($phone)){flash('error','Guardian phone number must contain exactly 11 digits.');Auth::redirect('/students/'.$studentId.'?edit_guardian='.$guardianRecordId);}
        $guardianFieldErrors=$this->validateGuardianFields($first,$last,$relationship,$email,$phone,trim($_POST['address']??''),trim($_POST['occupation']??''));
        if($guardianFieldErrors!==[]){flash('error',$guardianFieldErrors[0]);Auth::redirect('/students/'.$studentId.'?edit_guardian='.$guardianRecordId);}

        try{
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE guardians SET first_name=?,last_name=?,phone=?,email=?,address=?,occupation=?,updated_at=NOW() WHERE id=?')->execute([$first,$last,$phone?:null,$email?:null,trim($_POST['address']??'')?:null,trim($_POST['occupation']??'')?:null,$guardianRecordId]);
            $pdo->prepare('UPDATE student_guardians SET relationship=?,is_primary=?,can_pick_up=? WHERE student_id=? AND guardian_id=?')->execute([$relationship,!empty($_POST['is_primary'])?1:0,!empty($_POST['can_pick_up'])?1:0,$studentId,$guardianRecordId]);
            $pdo->commit();
            Auth::audit('students.guardian_updated','students',(string)$studentId,null,['guardian_id'=>$guardianRecordId]);
            flash('success','Guardian details updated.');
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('[guardian-update] '.$e->getMessage());
            flash('error','The guardian could not be updated: '.$e->getMessage());
        }
        Auth::redirect('/students/'.$studentId);
    }

    private function validateGuardianFields(string $firstName, string $lastName, string $relationship, string $email, string $phone, string $address, string $occupation): array
    {
        $errors=[];
        if (mb_strlen($firstName, 'UTF-8') > 80) $errors[] = 'Guardian first name is too long.';
        if (mb_strlen($lastName, 'UTF-8') > 80) $errors[] = 'Guardian last name is too long.';
        if (mb_strlen($relationship, 'UTF-8') > 50) $errors[] = 'Guardian relationship is too long.';
        if ($email !== '' && mb_strlen($email, 'UTF-8') > 191) $errors[] = 'Guardian email is too long.';
        if ($phone !== '' && mb_strlen($phone, 'UTF-8') > 30) $errors[] = 'Guardian phone number is too long.';
        if (mb_strlen($address, 'UTF-8') > 65535) $errors[] = 'Guardian address is too long.';
        if (mb_strlen($occupation, 'UTF-8') > 120) $errors[] = 'Guardian occupation is too long.';
        return $errors;
    }

    public function temporaryPassword(string $id): void
    {
        $studentId=(int)$id;
        if(!Authorization::canResetStudentPassword($studentId)){$this->deny('You may generate temporary passwords only for linked student accounts.');return;}
        $student=$this->findRaw($studentId);
        if(!$student||empty($student['user_id'])){$this->deny('This student profile is not linked to a login account.');return;}
        $pdo=Database::connection();
        try{
            $pdo->beginTransaction();
            $credentials=AccountProvisioner::resetTemporaryPassword($pdo,(int)$student['user_id']);
            Auth::audit('students.temporary_password_generated','students',(string)$studentId,null,['user_id'=>(int)$student['user_id']]);
            $pdo->commit();
            $credentials['notice']='Student temporary password generated';
            flash('account_credentials',json_encode($credentials));
            flash('success','The student must replace the temporary password at the next login.');
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','The temporary password could not be generated.');}
        Auth::redirect('/students/'.$studentId);
    }

    private function save(?int $id=null): void
    {
        $creating = $id === null;
        $selfEditing = !$creating && Authorization::isStudentOnly();
        $existing = $id ? $this->findRaw($id) : null;
        if ($id && !$existing) { $this->find($id); return; }
        $input=$this->input();
        if ($selfEditing && $existing) {
            foreach (['student_no','admission_date','student_status'] as $protectedField) {
                $input[$protectedField] = $existing[$protectedField] ?? '';
            }
        }
        $errors=array_merge(Validator::required($input,['student_no'=>'Student number','first_name'=>'First name','last_name'=>'Last name']),Validator::email($input['email']),Validator::phone($input['phone']),Validator::date($input['birth_date'],'birth_date','Birth date'));
        if($errors){View::render('students/form',array_merge($this->formData($id?$this->findRaw($id):null,$errors,$input),['selfEditing'=>$selfEditing]));return;}
        $pdo=Database::connection();
        $credentials=null;
        try{$pdo->beginTransaction();$values=[$input['student_no'],$input['first_name'],$input['middle_name']?:null,$input['last_name'],$input['suffix']?:null,$input['sex']?:null,$input['birth_date']?:null,$input['phone']?:null,$input['email']?:null,$input['address']?:null,$input['admission_date']?:null,$input['student_status']];
            if($id){$values[]=$id;$pdo->prepare('UPDATE students SET student_no=?,first_name=?,middle_name=?,last_name=?,suffix=?,sex=?,birth_date=?,phone=?,email=?,address=?,admission_date=?,student_status=? WHERE id=?')->execute($values);}
            else{$pdo->prepare('INSERT INTO students(student_no,first_name,middle_name,last_name,suffix,sex,birth_date,phone,email,address,admission_date,student_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)$pdo->lastInsertId();}
            if(!$selfEditing && Authorization::allows('enrollments.manage') && !$this->syncEnrollment($pdo,$id)) throw new \InvalidArgumentException('The selected section does not belong to the selected school year.');
            if($creating){$credentials=AccountProvisioner::createForProfile($pdo,'students',$id,$input['student_no'],$input['email'],$input['first_name'].' '.$input['last_name'],'Student');}
            else{AccountProvisioner::syncLinkedAccount($pdo,'students',$id,$input['student_no'],$input['email'],$input['first_name'].' '.$input['last_name']);}
            $pdo->commit();Auth::audit($creating?'students.created':'students.updated','students',(string)$id,null,$input);flash('success','Student profile saved.'.($creating?' A login account was created automatically.':''));if($credentials)flash('account_credentials',json_encode($credentials));Auth::redirect('/students/'.$id);
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors['form']=$e instanceof \PDOException?'Student number or another unique value is already in use.':$e->getMessage();View::render('students/form',array_merge($this->formData($id?$this->findRaw($id):null,$errors,$input),['selfEditing'=>$selfEditing]));}
    }
    private function syncEnrollment(\PDO $pdo,int $studentId): bool
    { $year=(int)($_POST['school_year_id']??0);$section=(int)($_POST['section_id']??0);if(!$year&&!$section)return true;if(!$year||!$section)return false;$check=$pdo->prepare('SELECT id FROM sections WHERE id=? AND school_year_id=?');$check->execute([$section,$year]);if(!$check->fetch())return false;$pdo->prepare('INSERT INTO enrollments(student_id,school_year_id,section_id,enrolled_on,status) VALUES(?,?,?,?,"enrolled") ON DUPLICATE KEY UPDATE section_id=VALUES(section_id),status="enrolled",updated_at=NOW()')->execute([$studentId,$year,$section,date('Y-m-d')]);return true;}
    private function input():array{return ['student_no'=>trim($_POST['student_no']??''),'first_name'=>trim($_POST['first_name']??''),'middle_name'=>trim($_POST['middle_name']??''),'last_name'=>trim($_POST['last_name']??''),'suffix'=>trim($_POST['suffix']??''),'sex'=>trim($_POST['sex']??''),'birth_date'=>trim($_POST['birth_date']??''),'phone'=>trim($_POST['phone']??''),'email'=>trim($_POST['email']??''),'address'=>trim($_POST['address']??''),'admission_date'=>trim($_POST['admission_date']??''),'student_status'=>trim($_POST['student_status']??'active')];}
    private function formData(?array $student,array $errors,array $input):array{$pdo=Database::connection();return compact('student','errors','input')+['selfEditing'=>false,'schoolYears'=>$pdo->query('SELECT * FROM school_years ORDER BY starts_on DESC')->fetchAll(),'sections'=>$pdo->query('SELECT s.*,g.name grade_level,sy.name school_year FROM sections s JOIN grade_levels g ON g.id=s.grade_level_id JOIN school_years sy ON sy.id=s.school_year_id WHERE s.status="active" ORDER BY sy.starts_on DESC,g.sequence_no,s.name')->fetchAll()];}
    private function findRaw(int $id):?array{$s=Database::connection()->prepare('SELECT * FROM students WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
    private function find(int $id):?array{$student=$this->findRaw($id);if(!$student){http_response_code(404);View::render('errors/message',['title'=>'Student not found','message'=>'The requested student profile does not exist.']);return null;}return $student;}
    private function canCreate():bool{if(Authorization::isStudentAdministrator()&&Authorization::allows('students.create'))return true;$this->deny();return false;}
    private function deny(string $message='You do not have permission to access this student profile.'):void{http_response_code(403);View::render('errors/message',['title'=>'Access denied','message'=>$message]);}
}
