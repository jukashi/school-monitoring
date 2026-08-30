<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use PDO;
use Throwable;

final class AttendanceController
{
    private const STUDENT_STATUSES = ['present','absent','late','excused','unrecorded'];
    private const TEACHER_STATUSES = ['present','absent','late','excused','on_leave','unrecorded'];

    public function students(): void
    {
        $pdo=Database::connection();$sectionId=(int)($_GET['section_id']??0);$date=$this->validDate($_GET['date']??date('Y-m-d'));
        $sections=$pdo->query('SELECT s.id,s.name,g.name grade_level,sy.name school_year FROM sections s JOIN grade_levels g ON g.id=s.grade_level_id JOIN school_years sy ON sy.id=s.school_year_id WHERE s.status="active" ORDER BY sy.starts_on DESC,g.sequence_no,s.name')->fetchAll();
        $students=[];$session=null;
        if($sectionId){$session=$this->studentSession($pdo,$sectionId,$date);$stmt=$pdo->prepare('SELECT st.id,st.student_no,st.first_name,st.last_name,COALESCE(sa.status,"unrecorded") attendance_status,sa.arrival_time,sa.remarks FROM enrollments e JOIN students st ON st.id=e.student_id LEFT JOIN student_attendance sa ON sa.student_id=st.id AND sa.attendance_session_id=? WHERE e.section_id=? AND e.status="enrolled" AND st.student_status="active" ORDER BY st.last_name,st.first_name');$stmt->execute([$session['id']??0,$sectionId]);$students=$stmt->fetchAll();}
        View::render('attendance/students',compact('sections','sectionId','date','students','session')+['statuses'=>self::STUDENT_STATUSES,'canRecord'=>Authorization::allows('attendance.record')]);
    }

    public function recordStudents(): void
    {
        $pdo=Database::connection();$sectionId=(int)($_POST['section_id']??0);$date=$this->validDate($_POST['date']??'');
        if(!$sectionId){flash('error','Select a section.');Auth::redirect('/attendance/students');}
        $check=$pdo->prepare('SELECT id FROM sections WHERE id=?');$check->execute([$sectionId]);if(!$check->fetch()){flash('error','The selected section does not exist.');Auth::redirect('/attendance/students');}
        $pdo->beginTransaction();
        try{$session=$this->studentSession($pdo,$sectionId,$date);if(!$session){$pdo->prepare('INSERT INTO attendance_sessions(section_id,attendance_date,session_type,recorded_by) VALUES(?,?,"daily",?)')->execute([$sectionId,$date,Auth::user()['id']]);$sessionId=(int)$pdo->lastInsertId();}else{$sessionId=(int)$session['id'];$pdo->prepare('UPDATE attendance_sessions SET recorded_by=?,updated_at=NOW() WHERE id=?')->execute([Auth::user()['id'],$sessionId]);}
            $allowed=$pdo->prepare('SELECT st.id FROM enrollments e JOIN students st ON st.id=e.student_id WHERE e.section_id=? AND e.status="enrolled" AND st.id=?');
            $save=$pdo->prepare('INSERT INTO student_attendance(attendance_session_id,student_id,status,arrival_time,remarks,recorded_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),arrival_time=VALUES(arrival_time),remarks=VALUES(remarks),recorded_by=VALUES(recorded_by),updated_at=NOW()');
            foreach($_POST['attendance']??[] as $studentId=>$row){$studentId=(int)$studentId;$status=(string)($row['status']??'unrecorded');if(!in_array($status,self::STUDENT_STATUSES,true))continue;$allowed->execute([$sectionId,$studentId]);if(!$allowed->fetch())continue;$arrival=$this->validTime($row['arrival_time']??'');$remarks=substr(trim($row['remarks']??''),0,500)?:null;$save->execute([$sessionId,$studentId,$status,$arrival,$remarks,Auth::user()['id']]);}
            $pdo->commit();Auth::audit('attendance.students_recorded','attendance_sessions',(string)$sessionId,null,['section_id'=>$sectionId,'date'=>$date]);flash('success','Student attendance saved.');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','Attendance could not be saved: '.$e->getMessage());}
        Auth::redirect('/attendance/students?section_id='.$sectionId.'&date='.$date);
    }

    public function teachers(): void
    {
        $pdo=Database::connection();$date=$this->validDate($_GET['date']??date('Y-m-d'));$stmt=$pdo->prepare('SELECT t.id,t.employee_no,t.first_name,t.last_name,d.name department,COALESCE(ta.status,"unrecorded") attendance_status,ta.time_in,ta.time_out,ta.remarks FROM teachers t LEFT JOIN departments d ON d.id=t.department_id LEFT JOIN teacher_attendance ta ON ta.teacher_id=t.id AND ta.attendance_date=? WHERE t.employment_status IN ("active","on_leave") ORDER BY t.last_name,t.first_name');$stmt->execute([$date]);View::render('attendance/teachers',['teachers'=>$stmt->fetchAll(),'date'=>$date,'statuses'=>self::TEACHER_STATUSES,'canRecord'=>Authorization::allows('attendance.record')]);
    }

    public function recordTeachers(): void
    {
        $pdo=Database::connection();$date=$this->validDate($_POST['date']??'');$save=$pdo->prepare('INSERT INTO teacher_attendance(teacher_id,attendance_date,status,time_in,time_out,remarks,recorded_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),time_in=VALUES(time_in),time_out=VALUES(time_out),remarks=VALUES(remarks),recorded_by=VALUES(recorded_by),updated_at=NOW()');$pdo->beginTransaction();
        try{foreach($_POST['attendance']??[] as $teacherId=>$row){$teacherId=(int)$teacherId;$status=(string)($row['status']??'unrecorded');if(!in_array($status,self::TEACHER_STATUSES,true))continue;$check=$pdo->prepare('SELECT id FROM teachers WHERE id=?');$check->execute([$teacherId]);if(!$check->fetch())continue;$save->execute([$teacherId,$date,$status,$this->validTime($row['time_in']??''),$this->validTime($row['time_out']??''),substr(trim($row['remarks']??''),0,500)?:null,Auth::user()['id']]);}$pdo->commit();Auth::audit('attendance.teachers_recorded','teacher_attendance',$date);flash('success','Teacher attendance saved.');}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','Attendance could not be saved: '.$e->getMessage());}
        Auth::redirect('/attendance/teachers?date='.$date);
    }

    private function studentSession(PDO $pdo,int $sectionId,string $date):?array{$stmt=$pdo->prepare('SELECT * FROM attendance_sessions WHERE section_id=? AND attendance_date=? AND session_type="daily" AND section_subject_id IS NULL ORDER BY id LIMIT 1');$stmt->execute([$sectionId,$date]);return $stmt->fetch()?:null;}
    private function validDate(string $date):string{$parsed=DateTimeImmutable::createFromFormat('Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date?$date:date('Y-m-d');}
    private function validTime(string $time):?string{if($time==='')return null;return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)?$time.':00':null;}
}

