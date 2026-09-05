<?php
$pageTitle='Messages'; $activeAdmin='messages';
require_once dirname(__DIR__).'/includes/admin_header.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $action=$_POST['action']??'';
    if($action==='read'){$pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([(int)($_POST['msg_id']??0)]);}
    elseif($action==='delete'){$pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)($_POST['msg_id']??0)]); flash('success','Message deleted.');}
    redirect(BASE_URL.'/admin/messages.php');
}
$messages=$pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$unread=(int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
?>
<div class="page-heading"><div><div class="eyebrow">Communications <span></span></div><h1>Contact Messages</h1><p><?=$unread?> unread message<?=$unread!=1?'s':''?>.</p></div></div>
<?php if(empty($messages)):?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)"><div style="font-size:40px;margin-bottom:12px">💬</div><p style="color:var(--ink-soft)">No messages yet. Messages from the contact form will appear here.</p></div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach($messages as $msg):?>
  <div style="background:var(--surface);border:1px solid <?=$msg['is_read']?'var(--line)':'var(--primary)'?>;border-left:4px solid <?=$msg['is_read']?'var(--line)':'var(--primary)'?>;border-radius:var(--radius);padding:18px 22px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px"><strong style="font-size:14.5px"><?=e($msg['name'])?></strong><?php if(!$msg['is_read']):?><span class="status pending" style="font-size:11px">New</span><?php endif;?></div>
        <?php if($msg['phone']):?><div class="muted" style="font-size:13px">📞 <?=e($msg['phone'])?></div><?php endif;?>
        <div class="muted" style="font-size:12px;margin-top:3px"><?=date('M d, Y g:ia',strtotime($msg['created_at']))?></div>
      </div>
      <div style="display:flex;gap:8px">
        <?php if(!$msg['is_read']):?><form method="post" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="read"/><input type="hidden" name="msg_id" value="<?=$msg['id']?>"/><button type="submit" class="filter-button button-sm">✓ Mark Read</button></form><?php endif;?>
        <form method="post" onsubmit="return confirm('Delete?')" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"/><input type="hidden" name="msg_id" value="<?=$msg['id']?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">✕ Delete</button></form>
      </div>
    </div>
    <div style="margin-top:12px;padding:12px;background:var(--bg);border-radius:var(--radius-sm);font-size:14px;line-height:1.75;color:var(--ink-soft)"><?=nl2br(e($msg['message']))?></div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php';?>
