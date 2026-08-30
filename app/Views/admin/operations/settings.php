<?php
use App\Core\Csrf;
$hasSchoolLogo=!empty($settings['school_logo']);
$logoPositionY=max(0,min(100,(int)($settings['school_logo_position_y']??50)));
?>
<div class="page-heading"><div><p class="eyebrow">Operations</p><h1>School settings</h1><p>Maintain the local school identity and dashboard announcements.</p></div></div>
<section class="card form-section logo-settings-card">
    <div class="logo-settings-copy">
        <p class="eyebrow">Branding</p><h2>School logo</h2>
        <p>Upload a logo, then drag it up or down in the sidebar preview to choose the best visible area.</p>
        <small>PNG, JPG, or WebP · Maximum 2 MB</small>
    </div>
    <div class="logo-settings-tools">
        <form method="post" action="<?=e(url('/admin/settings/logo'))?>" enctype="multipart/form-data" class="logo-upload-form">
            <?=Csrf::field()?>
            <label>Choose logo<input required type="file" name="school_logo" accept="image/png,image/jpeg,image/webp"></label>
            <button class="button primary">Upload school logo</button>
        </form>
        <form method="post" action="<?=e(url('/admin/settings/logo-position'))?>" class="logo-position-form" data-logo-position-form>
            <?=Csrf::field()?>
            <div>
                <div class="logo-position-heading"><strong>Adjust sidebar position</strong><span data-position-value><?=$logoPositionY?>%</span></div>
                <p class="logo-position-help"><?=$hasSchoolLogo?'Drag the image vertically or use the arrow keys. The sidebar updates live; save to keep the position.':'Upload a logo to enable positioning.'?></p>
            </div>
            <div class="logo-position-preview<?=$hasSchoolLogo?'':' is-disabled'?>" data-logo-position-preview role="slider" aria-label="School logo vertical position" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$logoPositionY?>" aria-disabled="<?=$hasSchoolLogo?'false':'true'?>" <?=$hasSchoolLogo?'tabindex="0"':''?> <?php if($hasSchoolLogo):?>style="background-image:url('<?=e(url('/'.$settings['school_logo']))?>');background-position:center <?=$logoPositionY?>%"<?php endif;?>>
                <?php if($hasSchoolLogo):?><img data-logo-position-image src="<?=e(url('/'.$settings['school_logo']))?>" alt="Current school logo"><span class="drag-hint" aria-hidden="true">↕ Drag</span><?php else:?><span class="logo-empty-mark">SM</span><?php endif;?>
            </div>
            <input type="hidden" name="position_y" value="<?=$logoPositionY?>" data-position-input>
            <button class="button" <?=$hasSchoolLogo?'':'disabled'?>>Save logo position</button>
        </form>
    </div>
</section>
<div class="detail-grid">
    <section class="card form-section"><h2>School information</h2><form method="post" action="<?=e(url('/admin/settings'))?>" class="stack-form"><?=Csrf::field()?><label>School name<input name="school_name" value="<?=e($settings['school_name']??'')?>"></label><label>Address<textarea name="school_address" rows="3"><?=e($settings['school_address']??'')?></textarea></label><div class="grid two"><label>Phone<input type="text" inputmode="numeric" autocomplete="tel" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" name="school_phone" value="<?=e($settings['school_phone']??'')?>"></label><label>Email<input type="email" name="school_email" value="<?=e($settings['school_email']??'')?>"></label><label>Late after<input type="time" name="attendance_late_after" value="<?=e($settings['attendance_late_after']??'08:00')?>"></label><label>Timezone<input name="timezone" value="<?=e($settings['timezone']??'Asia/Taipei')?>"></label></div><button class="button primary">Save settings</button></form></section>
    <section class="card form-section"><h2>Publish announcement</h2><form method="post" action="<?=e(url('/admin/announcements'))?>" class="stack-form"><?=Csrf::field()?><label>Title<input required name="title"></label><label>Message<textarea required name="body" rows="4"></textarea></label><label>Audience<select name="audience"><?php foreach(['all','students','teachers','staff'] as $x):?><option value="<?=$x?>"><?=e(ucfirst($x))?></option><?php endforeach;?></select></label><div class="grid two"><label>Publish at<input type="datetime-local" name="published_at"></label><label>Expires at<input type="datetime-local" name="expires_at"></label></div><button class="button primary">Publish announcement</button></form></section>
</div>
<section class="card table-card section-card"><h2>Recent announcements</h2><table><thead><tr><th>Title</th><th>Audience</th><th>Published</th><th>Expires</th><th>Creator</th></tr></thead><tbody><?php foreach($announcements as $a):?><tr><td><strong><?=e($a['title'])?></strong><br><small><?=e(mb_strimwidth($a['body'],0,100,'…'))?></small></td><td><?=e($a['audience'])?></td><td><?=e($a['published_at']?:'Draft')?></td><td><?=e($a['expires_at']?:'No expiry')?></td><td><?=e($a['creator'])?></td></tr><?php endforeach;?><?php if(!$announcements):?><tr><td colspan="5" class="empty">No announcements yet.</td></tr><?php endif;?></tbody></table></section>
<script>
(()=>{
    const form=document.querySelector('[data-logo-position-form]');
    if(!form)return;
    const preview=form.querySelector('[data-logo-position-preview]');
    const image=form.querySelector('[data-logo-position-image]');
    const sidebarFrame=document.querySelector('[data-sidebar-logo-frame]');
    const input=form.querySelector('[data-position-input]');
    const output=form.querySelector('[data-position-value]');
    if(!preview||!image||!input||!output)return;
    const clamp=value=>Math.max(0,Math.min(100,Math.round(value)));
    const update=value=>{const next=clamp(value);const position='center '+next+'%';input.value=String(next);output.textContent=next+'%';preview.setAttribute('aria-valuenow',String(next));preview.style.backgroundPosition=position;if(sidebarFrame)sidebarFrame.style.backgroundPosition=position;};
    let startY=0,startValue=Number(input.value),dragging=false;
    preview.addEventListener('pointerdown',event=>{event.preventDefault();dragging=true;startY=event.clientY;startValue=Number(input.value);preview.setPointerCapture(event.pointerId);preview.classList.add('is-dragging');});
    preview.addEventListener('pointermove',event=>{if(!dragging)return;event.preventDefault();update(startValue-((event.clientY-startY)/preview.clientHeight)*100);});
    const stop=()=>{dragging=false;preview.classList.remove('is-dragging');};
    preview.addEventListener('pointerup',stop);preview.addEventListener('pointercancel',stop);preview.addEventListener('lostpointercapture',stop);
    preview.addEventListener('keydown',event=>{if(event.key!=='ArrowUp'&&event.key!=='ArrowDown'&&event.key!=='Home'&&event.key!=='End')return;event.preventDefault();if(event.key==='Home')update(0);else if(event.key==='End')update(100);else update(Number(input.value)+(event.key==='ArrowDown'?2:-2));});
})();
</script>
