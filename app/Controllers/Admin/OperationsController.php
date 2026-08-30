<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Auth;use App\Core\Database;use App\Core\View;use Throwable;
final class OperationsController
{
    public function settings():void{$pdo=Database::connection();$settings=[];foreach($pdo->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll() as $row)$settings[$row['setting_key']]=$row['setting_value'];$announcements=$pdo->query('SELECT a.*,u.display_name creator FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC LIMIT 50')->fetchAll();View::render('admin/operations/settings',compact('settings','announcements'));}
    public function updateSettings():void{$allowed=['school_name','school_address','school_phone','school_email','attendance_late_after','timezone'];$schoolPhone=trim($_POST['school_phone']??'');if($schoolPhone!==''&&!preg_match('/^\d{11}$/',$schoolPhone)){flash('error','School phone number must contain exactly 11 digits.');Auth::redirect('/admin/settings');}$pdo=Database::connection();$save=$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,updated_by) VALUES(?,?,"string",1,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()');foreach($allowed as $key)$save->execute([$key,substr(trim($_POST[$key]??''),0,500),Auth::user()['id']]);Auth::audit('settings.updated','system_settings',null);flash('success','School settings saved.');Auth::redirect('/admin/settings');}
    public function addAnnouncement():void{$title=trim($_POST['title']??'');$body=trim($_POST['body']??'');$audience=$_POST['audience']??'all';if($title===''||$body===''||!in_array($audience,['all','students','teachers','staff'],true)){flash('error','Announcement title, message, and audience are required.');Auth::redirect('/admin/settings');}$published=$this->dateTime($_POST['published_at']??'')??date('Y-m-d H:i:s');$expires=$this->dateTime($_POST['expires_at']??'');Database::connection()->prepare('INSERT INTO announcements(title,body,audience,published_at,expires_at,created_by) VALUES(?,?,?,?,?,?)')->execute([$title,$body,$audience,$published,$expires,Auth::user()['id']]);$id=(string)Database::connection()->lastInsertId();Auth::audit('announcements.created','announcements',$id);flash('success','Announcement published.');Auth::redirect('/admin/settings');}
    public function uploadLogo():void
    {
        $file=$_FILES['school_logo']??null;
        if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){flash('error','Choose a valid logo image to upload.');Auth::redirect('/admin/settings');}
        if((int)$file['size']>2*1024*1024){flash('error','The school logo must be 2 MB or smaller.');Auth::redirect('/admin/settings');}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if(!isset($extensions[$mime])||!@getimagesize($file['tmp_name'])){flash('error','Upload a PNG, JPG, or WebP image.');Auth::redirect('/admin/settings');}
        $directory=APP_ROOT.'/public/uploads/branding';if(!is_dir($directory)&&!mkdir($directory,0775,true)){flash('error','The branding upload folder could not be created.');Auth::redirect('/admin/settings');}
        $fileName='school-logo-'.bin2hex(random_bytes(6)).'.'.$extensions[$mime];$destination=$directory.'/'.$fileName;
        if(!move_uploaded_file($file['tmp_name'],$destination)){flash('error','The school logo could not be saved.');Auth::redirect('/admin/settings');}
        $pdo=Database::connection();$old=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key="school_logo"');$old->execute();$oldPath=$old->fetchColumn();
        $publicPath='uploads/branding/'.$fileName;$pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,updated_by) VALUES("school_logo",?,"string",1,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()')->execute([$publicPath,Auth::user()['id']]);
        if(is_string($oldPath)&&preg_match('#^uploads/branding/school-logo-[a-f0-9]{12}\.(png|jpg|webp)$#',$oldPath)){ $oldFile=APP_ROOT.'/public/'.$oldPath;if(is_file($oldFile))unlink($oldFile); }
        Auth::audit('settings.logo_updated','system_settings','school_logo');flash('success','School logo uploaded and fitted to the sidebar.');Auth::redirect('/admin/settings');
    }
    public function updateLogoPosition():void
    {
        $position=max(0,min(100,(int)($_POST['position_y']??50)));
        $pdo=Database::connection();
        $hasLogo=$pdo->query('SELECT COUNT(*) FROM system_settings WHERE setting_key="school_logo" AND setting_value IS NOT NULL AND setting_value<>""')->fetchColumn();
        if(!$hasLogo){flash('error','Upload a school logo before adjusting its position.');Auth::redirect('/admin/settings');}
        $pdo->prepare('INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,updated_by) VALUES("school_logo_position_y",?,"integer",1,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type="integer",updated_by=VALUES(updated_by),updated_at=NOW()')->execute([(string)$position,Auth::user()['id']]);
        Auth::audit('settings.logo_position_updated','system_settings','school_logo_position_y',null,['position_y'=>$position]);
        flash('success','Logo position updated.');Auth::redirect('/admin/settings');
    }
    public function audit():void{$q=trim($_GET['q']??'');$action=trim($_GET['action']??'');$sql='SELECT a.*,u.display_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE 1=1';$args=[];if($q!==''){$sql.=' AND (u.display_name LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ?)';$like='%'.$q.'%';$args=[$like,$like,$like];}if($action!==''){$sql.=' AND a.action LIKE ?';$args[]='%'.$action.'%';}$sql.=' ORDER BY a.created_at DESC LIMIT 500';$s=Database::connection()->prepare($sql);$s->execute($args);View::render('admin/operations/audit',['logs'=>$s->fetchAll(),'q'=>$q,'action'=>$action]);}
    private function dateTime(string $value):?string{if($value==='')return null;try{return(new \DateTimeImmutable($value))->format('Y-m-d H:i:s');}catch(Throwable $e){return null;}}
}
