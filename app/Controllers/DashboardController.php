<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\View;
use App\Core\Database;

final class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo=Database::connection();
        $summary=[
            'students'=>(int)$pdo->query('SELECT COUNT(*) FROM students WHERE student_status="active"')->fetchColumn(),
            'teachers'=>(int)$pdo->query('SELECT COUNT(*) FROM teachers WHERE employment_status="active"')->fetchColumn(),
            'student_absences'=>(int)$pdo->query('SELECT COUNT(*) FROM student_attendance sa JOIN attendance_sessions s ON s.id=sa.attendance_session_id WHERE s.attendance_date=CURDATE() AND sa.status="absent"')->fetchColumn(),
            'teacher_absences'=>(int)$pdo->query('SELECT COUNT(*) FROM teacher_attendance WHERE attendance_date=CURDATE() AND status="absent"')->fetchColumn(),
            'upcoming_events'=>(int)$pdo->query('SELECT COUNT(*) FROM events WHERE starts_at>=NOW() AND status IN ("published","ongoing")')->fetchColumn(),
            'insurance_expiring'=>(int)$pdo->query('SELECT COUNT(*) FROM insurance_policies WHERE status="active" AND coverage_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)')->fetchColumn(),
            'enrolled_students'=>(int)$pdo->query('SELECT COUNT(DISTINCT st.id) FROM students st JOIN enrollments e ON e.student_id=st.id AND e.status="enrolled" WHERE st.student_status="active"')->fetchColumn(),
            'students_without_enrollment'=>(int)$pdo->query('SELECT COUNT(*) FROM students st WHERE st.student_status="active" AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id=st.id AND e.status="enrolled")')->fetchColumn(),
            'incomplete_student_profiles'=>(int)$pdo->query('SELECT COUNT(*) FROM students WHERE student_status="active" AND (email IS NULL OR email="" OR phone IS NULL OR phone="" OR birth_date IS NULL OR address IS NULL OR address="")')->fetchColumn(),
            'transfers_withdrawals'=>(int)$pdo->query('SELECT COUNT(*) FROM students WHERE student_status IN ("transferred","withdrawn")')->fetchColumn(),
        ];
        $upcoming=$pdo->query('SELECT id,title,venue,starts_at,status FROM events WHERE starts_at>=NOW() AND status IN ("published","ongoing") ORDER BY starts_at LIMIT 5')->fetchAll();
        $roleStmt=$pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $roleStmt->execute([Auth::user()['id']]);
        $roleNames=array_column($roleStmt->fetchAll(),'name');
        $audiences=['all'];
        if(in_array('Super Administrator',$roleNames,true))$audiences=['all','students','teachers','staff'];
        else{if(in_array('Student',$roleNames,true))$audiences[]='students';if(in_array('Teacher',$roleNames,true))$audiences[]='teachers';if(array_intersect(['Administrator','Administrator / Registrar','Registrar','Viewer / Staff'],$roleNames))$audiences[]='staff';}
        $placeholders=implode(',',array_fill(0,count($audiences),'?'));
        $announcementStmt=$pdo->prepare("SELECT title,body,audience,published_at FROM announcements WHERE audience IN ({$placeholders}) AND published_at<=NOW() AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY published_at DESC LIMIT 5");
        $announcementStmt->execute($audiences);
        $announcements=$announcementStmt->fetchAll();
        $birthdays=[];
        if(Authorization::allows('students.view')&&Authorization::allows('teachers.view')){
            $birthdays=$pdo->query('SELECT "student" person_type,id,student_no reference_no,first_name,last_name,birth_date,DAY(birth_date) birthday_day,YEAR(CURDATE())-YEAR(birth_date) turning_age FROM students WHERE student_status="active" AND birth_date IS NOT NULL AND MONTH(birth_date)=MONTH(CURDATE()) UNION ALL SELECT "teacher" person_type,id,employee_no reference_no,first_name,last_name,birth_date,DAY(birth_date) birthday_day,YEAR(CURDATE())-YEAR(birth_date) turning_age FROM teachers WHERE employment_status="active" AND birth_date IS NOT NULL AND MONTH(birth_date)=MONTH(CURDATE()) ORDER BY birthday_day,last_name,first_name')->fetchAll();
        }
        View::render('dashboard/index', ['user' => Auth::user(), 'permissions' => Authorization::permissions(), 'summary'=>$summary, 'upcoming'=>$upcoming, 'announcements'=>$announcements, 'birthdays'=>$birthdays]);
    }
}
