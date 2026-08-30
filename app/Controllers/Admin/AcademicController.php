<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class AcademicController
{
    public function index(): void
    {
        $pdo = Database::connection();
        View::render('admin/academics/index', [
            'schoolYears' => $pdo->query('SELECT * FROM school_years ORDER BY starts_on DESC')->fetchAll(),
            'departments' => $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll(),
            'grades' => $pdo->query('SELECT * FROM grade_levels ORDER BY sequence_no')->fetchAll(),
            'subjects' => $pdo->query('SELECT * FROM subjects ORDER BY name')->fetchAll(),
            'terms' => $pdo->query('SELECT t.*,sy.name school_year FROM terms t JOIN school_years sy ON sy.id=t.school_year_id ORDER BY sy.starts_on DESC,t.sequence_no')->fetchAll(),
            'sections' => $pdo->query('SELECT s.*, sy.name school_year, g.name grade_level, CONCAT(t.last_name, ", ", t.first_name) adviser FROM sections s JOIN school_years sy ON sy.id=s.school_year_id JOIN grade_levels g ON g.id=s.grade_level_id LEFT JOIN teachers t ON t.id=s.adviser_teacher_id ORDER BY sy.starts_on DESC,g.sequence_no,s.name')->fetchAll(),
            'teachers' => $pdo->query('SELECT id, employee_no, first_name, last_name FROM teachers WHERE employment_status="active" ORDER BY last_name,first_name')->fetchAll(),
            'assignments' => $pdo->query('SELECT ta.id, ta.is_primary, t.employee_no, CONCAT(t.last_name, ", ", t.first_name) teacher_name, sub.code subject_code, sub.name subject_name, sy.name school_year, g.name grade_level, s.name section_name, tr.name term_name, ss.schedule_text, COALESCE(ss.room,s.room) room FROM teacher_assignments ta JOIN teachers t ON t.id=ta.teacher_id JOIN section_subjects ss ON ss.id=ta.section_subject_id JOIN subjects sub ON sub.id=ss.subject_id JOIN sections s ON s.id=ss.section_id JOIN school_years sy ON sy.id=s.school_year_id JOIN grade_levels g ON g.id=s.grade_level_id LEFT JOIN terms tr ON tr.id=ss.term_id ORDER BY sy.starts_on DESC,g.sequence_no,s.name,sub.name,t.last_name')->fetchAll(),
        ]);
    }

    public function store(string $type): void
    {
        $pdo = Database::connection();
        try {
            switch ($type) {
                case 'school-year':
                    $name=trim($_POST['name']??''); $start=$_POST['starts_on']??''; $end=$_POST['ends_on']??'';
                    if ($name===''||$start===''||$end===''||$end<$start) throw new \InvalidArgumentException('Enter a name and a valid date range.');
                    if (!empty($_POST['is_active'])) $pdo->exec('UPDATE school_years SET is_active=0');
                    $pdo->prepare('INSERT INTO school_years(name,starts_on,ends_on,is_active) VALUES(?,?,?,?)')->execute([$name,$start,$end,!empty($_POST['is_active'])?1:0]);
                    break;
                case 'term':
                    $year=(int)($_POST['school_year_id']??0);$name=trim($_POST['name']??'');$start=trim($_POST['starts_on']??'');$end=trim($_POST['ends_on']??'');$sequence=(int)($_POST['sequence_no']??0);
                    if(!$year||$name===''||$start===''||$end===''||$sequence<1||$end<$start)throw new \InvalidArgumentException('Choose a school year and enter a term name, valid dates, and positive display order.');
                    $yearCheck=$pdo->prepare('SELECT starts_on,ends_on FROM school_years WHERE id=?');$yearCheck->execute([$year]);$yearDates=$yearCheck->fetch();
                    if(!$yearDates||$start<$yearDates['starts_on']||$end>$yearDates['ends_on'])throw new \InvalidArgumentException('Term dates must fall within the selected school year.');
                    $pdo->prepare('INSERT INTO terms(school_year_id,name,starts_on,ends_on,sequence_no) VALUES(?,?,?,?,?)')->execute([$year,$name,$start,$end,$sequence]);
                    break;
                case 'department':
                    $code=strtoupper(trim($_POST['code']??'')); $name=trim($_POST['name']??'');
                    if ($code===''||$name==='') throw new \InvalidArgumentException('Department code and name are required.');
                    $pdo->prepare('INSERT INTO departments(code,name) VALUES(?,?)')->execute([$code,$name]); break;
                case 'grade':
                    $code=strtoupper(trim($_POST['code']??'')); $name=trim($_POST['name']??''); $sequence=(int)($_POST['sequence_no']??0);
                    if ($code===''||$name===''||$sequence<1) throw new \InvalidArgumentException('Grade code, name, and positive sequence are required.');
                    $pdo->prepare('INSERT INTO grade_levels(code,name,sequence_no) VALUES(?,?,?)')->execute([$code,$name,$sequence]); break;
                case 'subject':
                    $code=strtoupper(trim($_POST['code']??'')); $name=trim($_POST['name']??'');
                    if ($code===''||$name==='') throw new \InvalidArgumentException('Subject code and name are required.');
                    $pdo->prepare('INSERT INTO subjects(code,name,description) VALUES(?,?,?)')->execute([$code,$name,trim($_POST['description']??'')?:null]); break;
                case 'section':
                    $year=(int)($_POST['school_year_id']??0); $grade=(int)($_POST['grade_level_id']??0); $name=trim($_POST['name']??'');
                    if (!$year||!$grade||$name==='') throw new \InvalidArgumentException('School year, grade level, and section name are required.');
                    $pdo->prepare('INSERT INTO sections(school_year_id,grade_level_id,adviser_teacher_id,name,capacity,room) VALUES(?,?,?,?,?,?)')->execute([$year,$grade,($_POST['adviser_teacher_id']??'')?:null,$name,($_POST['capacity']??'')?:null,trim($_POST['room']??'')?:null]); break;
                case 'assignment':
                    $teacher=(int)($_POST['teacher_id']??0); $section=(int)($_POST['section_id']??0); $subject=(int)($_POST['subject_id']??0); $term=(int)($_POST['term_id']??0);
                    if (!$teacher||!$section||!$subject) throw new \InvalidArgumentException('Teacher, section, and subject are required.');
                    $pdo->beginTransaction();
                    $find=$pdo->prepare('SELECT id FROM section_subjects WHERE section_id=? AND subject_id=? AND term_id <=> ? LIMIT 1');
                    $find->execute([$section,$subject,$term?:null]);
                    $sectionSubjectId=(int)($find->fetchColumn()?:0);
                    if (!$sectionSubjectId) {
                        $pdo->prepare('INSERT INTO section_subjects(section_id,subject_id,term_id,schedule_text,room) VALUES(?,?,?,?,?)')->execute([$section,$subject,$term?:null,trim($_POST['schedule_text']??'')?:null,trim($_POST['room']??'')?:null]);
                        $sectionSubjectId=(int)$pdo->lastInsertId();
                    } else {
                        $pdo->prepare('UPDATE section_subjects SET schedule_text=?,room=? WHERE id=?')->execute([trim($_POST['schedule_text']??'')?:null,trim($_POST['room']??'')?:null,$sectionSubjectId]);
                    }
                    $pdo->prepare('INSERT INTO teacher_assignments(teacher_id,section_subject_id,is_primary) VALUES(?,?,?)')->execute([$teacher,$sectionSubjectId,!empty($_POST['is_primary'])?1:0]);
                    $pdo->commit();
                    break;
                default: throw new \InvalidArgumentException('Unknown academic record type.');
            }
            Auth::audit('academics.'.$type.'_created', $type, (string)$pdo->lastInsertId());
            flash('success', 'Academic record created.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e instanceof \PDOException ? 'The record conflicts with an existing value or invalid reference.' : $e->getMessage());
        }
        Auth::redirect('/admin/academics');
    }
}
