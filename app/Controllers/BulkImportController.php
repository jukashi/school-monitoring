<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AccountProvisioner;
use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use PDO;
use Throwable;

final class BulkImportController
{
    private const MAX_BYTES = 5242880;
    private const MAX_ROWS = 500;
    private const STUDENT_HEADERS = ['student_no','first_name','middle_name','last_name','suffix','sex','birth_date','phone','email','address','admission_date','status','school_year','grade_level','section'];
    private const TEACHER_HEADERS = ['employee_no','first_name','middle_name','last_name','suffix','sex','birth_date','phone','email','address','hire_date','status','department'];

    public function students(): void { if($this->requireStudentImporter())$this->page('students', []); }
    public function teachers(): void { if($this->requireTeacherAdministrator())$this->page('teachers', []); }
    public function studentTemplate(): void { if($this->requireStudentImporter())$this->template('students', self::STUDENT_HEADERS); }
    public function teacherTemplate(): void { if($this->requireTeacherAdministrator())$this->template('teachers', self::TEACHER_HEADERS); }

    public function importStudents(): void
    {
        if(!$this->requireStudentImporter())return;
        [$rows,$errors]=$this->csv(self::STUDENT_HEADERS);
        if (!$errors) [$rows,$errors]=$this->validateStudents($rows);
        if ($errors) { $this->page('students',$errors); return; }
        $pdo=Database::connection();
        $credentials=[];
        try {
            $pdo->beginTransaction();
            $insert=$pdo->prepare('INSERT INTO students(student_no,first_name,middle_name,last_name,suffix,sex,birth_date,phone,email,address,admission_date,student_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $enroll=$pdo->prepare('INSERT INTO enrollments(student_id,school_year_id,section_id,enrolled_on,status) VALUES(?,?,?,? ,"enrolled")');
            foreach($rows as $row){
                $insert->execute([$row['student_no'],$row['first_name'],$row['middle_name']?:null,$row['last_name'],$row['suffix']?:null,$row['sex']?:null,$row['birth_date']?:null,$row['phone']?:null,$row['email']?:null,$row['address']?:null,$row['admission_date']?:null,$row['status']]);
                $studentId=(int)$pdo->lastInsertId();if($row['_section_id'])$enroll->execute([$studentId,$row['_school_year_id'],$row['_section_id'],$row['admission_date']?:date('Y-m-d')]);
                $account=AccountProvisioner::createForProfile($pdo,'students',$studentId,$row['student_no'],$row['email'],$row['first_name'].' '.$row['last_name'],'Student');
                $credentials[]=['reference_no'=>$row['student_no'],'full_name'=>$row['first_name'].' '.$row['last_name'],'username'=>$account['username'],'temporary_password'=>$account['temporary_password'],'role'=>'Student'];
            }
            $pdo->commit();
            Auth::audit('students.bulk_imported','students',null,null,['count'=>count($rows)]);
            $this->credentialCsv('student',$credentials);
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$this->page('students',['The import was cancelled and no records were saved because the database rejected a value. Review the template and try again.']);}
    }

    public function importTeachers(): void
    {
        if(!$this->requireTeacherAdministrator())return;
        [$rows,$errors]=$this->csv(self::TEACHER_HEADERS);
        if (!$errors) [$rows,$errors]=$this->validateTeachers($rows);
        if ($errors) { $this->page('teachers',$errors); return; }
        $pdo=Database::connection();
        $credentials=[];
        try {
            $pdo->beginTransaction();
            $insert=$pdo->prepare('INSERT INTO teachers(employee_no,department_id,first_name,middle_name,last_name,suffix,sex,birth_date,phone,email,address,hire_date,employment_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach($rows as $row){
                $insert->execute([$row['employee_no'],$row['_department_id']?:null,$row['first_name'],$row['middle_name']?:null,$row['last_name'],$row['suffix']?:null,$row['sex']?:null,$row['birth_date']?:null,$row['phone']?:null,$row['email']?:null,$row['address']?:null,$row['hire_date']?:null,$row['status']]);
                $teacherId=(int)$pdo->lastInsertId();
                $account=AccountProvisioner::createForProfile($pdo,'teachers',$teacherId,$row['employee_no'],$row['email'],$row['first_name'].' '.$row['last_name'],'Teacher');
                $credentials[]=['reference_no'=>$row['employee_no'],'full_name'=>$row['first_name'].' '.$row['last_name'],'username'=>$account['username'],'temporary_password'=>$account['temporary_password'],'role'=>'Teacher'];
            }
            $pdo->commit();
            Auth::audit('teachers.bulk_imported','teachers',null,null,['count'=>count($rows)]);
            $this->credentialCsv('teacher',$credentials);
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$this->page('teachers',['The import was cancelled and no records were saved because the database rejected a value. Review the template and try again.']);}
    }

    private function page(string $type,array $errors): void
    {
        $student=$type==='students';
        View::render('imports/form',['type'=>$type,'errors'=>$errors,'title'=>$student?'Import students':'Import teachers','entity'=>$student?'student':'teacher','headers'=>$student?self::STUDENT_HEADERS:self::TEACHER_HEADERS]);
    }

    private function requireStudentImporter(): bool
    {
        if(Authorization::isStudentAdministrator() && Authorization::allows('students.import'))return true;
        http_response_code(403);
        View::render('errors/message',['title'=>'Access denied','message'=>'You do not have permission to import student records.']);
        return false;
    }

    private function requireTeacherAdministrator(): bool
    {
        if(Authorization::isSystemAdministrator() && Authorization::allows('teachers.create'))return true;
        http_response_code(403);
        View::render('errors/message',['title'=>'Access denied','message'=>'Only administrators can import teacher records.']);
        return false;
    }

    private function template(string $type,array $headers): void
    {
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$type.'-import-template.csv"');
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers);fclose($out);
    }

    private function csv(array $expected): array
    {
        $file=$_FILES['import_file']??null;$errors=[];
        if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return[[],['Choose a CSV file to import.']];
        if((int)($file['size']??0)>self::MAX_BYTES)$errors[]='The file exceeds the 5 MB limit.';
        if(strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='csv')$errors[]='Only CSV files are accepted. Download and use the provided template.';
        if($errors)return[[],$errors];
        $handle=fopen((string)$file['tmp_name'],'rb');if(!$handle)return[[],['The uploaded file could not be read.']];
        $header=fgetcsv($handle);if(!$header){fclose($handle);return[[],['The CSV file is empty.']];}
        $header=array_map(static fn($x)=>strtolower(trim((string)$x)), $header);$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
        if($header!==$expected){fclose($handle);return[[],['The header row does not match the template. Do not rename, remove, or reorder columns.']];}
        $rows=[];$line=1;
        while(($values=fgetcsv($handle))!==false){$line++;if(count($values)===1&&trim((string)$values[0])==='')continue;if(count($values)!==count($header)){$errors[]="Row {$line}: expected ".count($header).' columns but found '.count($values).'.';continue;}$row=array_combine($header,array_map(static fn($x)=>trim((string)$x),$values));$row['_line']=$line;$rows[]=$row;if(count($rows)>self::MAX_ROWS){$errors[]='The file exceeds the 500-record limit.';break;}}
        fclose($handle);if(!$rows&&!$errors)$errors[]='The file has a header but no records to import.';return[$rows,$errors];
    }

    private function validateStudents(array $rows): array
    {
        $pdo=Database::connection();$errors=[];$seen=[];$existingValues=array_merge($pdo->query('SELECT student_no FROM students')->fetchAll(PDO::FETCH_COLUMN),$pdo->query('SELECT username FROM users')->fetchAll(PDO::FETCH_COLUMN));$existing=array_fill_keys(array_map('strtolower',$existingValues),true);
        $years=[];foreach($pdo->query('SELECT id,name FROM school_years')->fetchAll() as $x)$years[strtolower($x['name'])]=(int)$x['id'];
        $sections=[];foreach($pdo->query('SELECT s.id,s.school_year_id,s.name section_name,g.name grade_name,g.code grade_code,sy.name school_year FROM sections s JOIN grade_levels g ON g.id=s.grade_level_id JOIN school_years sy ON sy.id=s.school_year_id')->fetchAll() as $x){foreach([$x['grade_name'],$x['grade_code']] as $grade)$sections[strtolower($x['school_year'].'|'.$grade.'|'.$x['section_name'])]=[(int)$x['id'],(int)$x['school_year_id']];}
        foreach($rows as &$row){$line=$row['_line'];foreach(['student_no'=>'student number','first_name'=>'first name','last_name'=>'last name'] as $key=>$label)if($row[$key]==='')$errors[]="Row {$line}: {$label} is required.";if($row['student_no']!==''&&(isset($seen[strtolower($row['student_no'])])||isset($existing[strtolower($row['student_no'])])))$errors[]="Row {$line}: student number {$row['student_no']} is duplicated or already exists.";$seen[strtolower($row['student_no'])]=true;$this->common($row,$line,['active','inactive','graduated','transferred','withdrawn'],'admission_date',$errors);$row['_section_id']=null;$row['_school_year_id']=null;$hasEnrollment=$row['school_year']!==''||$row['grade_level']!==''||$row['section']!=='';if($hasEnrollment){if($row['school_year']===''||$row['grade_level']===''||$row['section']==='')$errors[]="Row {$line}: school_year, grade_level, and section must all be supplied together.";else{$key=strtolower($row['school_year'].'|'.$row['grade_level'].'|'.$row['section']);if(!isset($sections[$key]))$errors[]="Row {$line}: the school year, grade level, and section do not match an existing section.";else[$row['_section_id'],$row['_school_year_id']]=$sections[$key];}}}
        unset($row);return[$rows,$errors];
    }

    private function validateTeachers(array $rows): array
    {
        $pdo=Database::connection();$errors=[];$seen=[];$existingValues=array_merge($pdo->query('SELECT employee_no FROM teachers')->fetchAll(PDO::FETCH_COLUMN),$pdo->query('SELECT username FROM users')->fetchAll(PDO::FETCH_COLUMN));$existing=array_fill_keys(array_map('strtolower',$existingValues),true);$departments=[];foreach($pdo->query('SELECT id,code,name FROM departments WHERE status="active"')->fetchAll() as $x){$departments[strtolower($x['code'])]=(int)$x['id'];$departments[strtolower($x['name'])]=(int)$x['id'];}
        foreach($rows as &$row){$line=$row['_line'];foreach(['employee_no'=>'employee number','first_name'=>'first name','last_name'=>'last name'] as $key=>$label)if($row[$key]==='')$errors[]="Row {$line}: {$label} is required.";if($row['employee_no']!==''&&(isset($seen[strtolower($row['employee_no'])])||isset($existing[strtolower($row['employee_no'])])))$errors[]="Row {$line}: employee number {$row['employee_no']} is duplicated or already exists.";$seen[strtolower($row['employee_no'])]=true;$this->common($row,$line,['active','on_leave','inactive','separated'],'hire_date',$errors);$row['_department_id']=null;if($row['department']!==''){if(!isset($departments[strtolower($row['department'])]))$errors[]="Row {$line}: department {$row['department']} does not match an active department code or name.";else$row['_department_id']=$departments[strtolower($row['department'])];}}
        unset($row);return[$rows,$errors];
    }

    private function common(array &$row,int $line,array $statuses,string $secondDate,array &$errors): void
    {
        $row['sex']=strtolower($row['sex']);$row['status']=strtolower($row['status']?:'active');
        if($row['sex']!==''&&!in_array($row['sex'],['male','female','other','prefer_not_to_say'],true))$errors[]="Row {$line}: sex must be male, female, other, prefer_not_to_say, or blank.";
        if(!in_array($row['status'],$statuses,true))$errors[]="Row {$line}: status is invalid.";
        foreach(['birth_date',$secondDate] as $field)if($row[$field]!==''&&!$this->date($row[$field]))$errors[]="Row {$line}: {$field} must use YYYY-MM-DD.";
        if($row['email']!==''&&!filter_var($row['email'],FILTER_VALIDATE_EMAIL))$errors[]="Row {$line}: email address is invalid.";
        if($row['phone']!==''&&!preg_match('/^\d{11}$/',$row['phone']))$errors[]="Row {$line}: phone must contain exactly 11 digits.";
    }

    private function credentialCsv(string $type,array $credentials): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$type.'-login-credentials-'.date('Ymd-His').'.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out=fopen('php://output','wb');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['reference_no','full_name','username','temporary_password','role']);
        foreach($credentials as $credential)fputcsv($out,array_map([$this,'csvSafe'],$credential));
        fclose($out);
        exit;
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/',$value)?"'".$value:$value;
    }

    private function date(string $value): bool {$d=DateTimeImmutable::createFromFormat('Y-m-d',$value);return$d&&$d->format('Y-m-d')===$value;}
}
