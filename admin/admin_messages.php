<?php
require_once 'auth_check.php';

$msg = ''; $mtype = 'ok';

// ── SEND REPLY (email the customer directly from the website) ───
if(isset($_POST['send_reply'])){
    csrf_check();
    $mid        = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE message_id=?");
    $stmt->bind_param("i", $mid);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();

    if(!$original){
        header("Location: admin_messages.php?msg=Message+not+found.&mtype=err"); exit;
    }
    if($reply_text === ''){
        header("Location: admin_messages.php?msg=Reply+cannot+be+empty.&mtype=err#msg-$mid"); exit;
    }

    // Build branded reply email quoting the customer's original message
    $name_safe  = htmlspecialchars($original['name'],    ENT_QUOTES, 'UTF-8');
    $subj_safe  = htmlspecialchars($original['subject'], ENT_QUOTES, 'UTF-8');
    $orig_safe  = nl2br(htmlspecialchars($original['message'], ENT_QUOTES, 'UTF-8'));
    $reply_safe = nl2br(htmlspecialchars($reply_text,          ENT_QUOTES, 'UTF-8'));

    $html = apex_mail_html_body(
        "<p>Hello {$name_safe},</p>"
        . "<p>Thank you for contacting Apex Store. Here is our reply to your message:</p>"
        . "<div style=\"background:#fff8f6;border-left:4px solid #C8543C;border-radius:6px;"
        .    "padding:18px 22px;margin:20px 0;font-size:.95rem;color:#2d2d2d;line-height:1.7;\">"
        . $reply_safe
        . "</div>"
        . "<div style=\"margin-top:24px;padding-top:16px;border-top:1px solid #eee;\">"
        . "<div style=\"font-size:.75rem;color:#888;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;\">Your original message</div>"
        . "<div style=\"font-size:.85rem;color:#666;line-height:1.6;\">"
        . "<strong>Subject:</strong> {$subj_safe}<br><br>{$orig_safe}"
        . "</div>"
        . "</div>"
    );

    $result = apex_send_mail($original['email'], $original['name'], 'Re: '.$original['subject'].' — Apex Store', $html);

    if($result['ok']){
        $upd = $conn->prepare("UPDATE contact_messages SET admin_reply=?, replied_at=NOW(), is_read=1 WHERE message_id=?");
        $upd->bind_param("si", $reply_text, $mid);
        $upd->execute();
        header("Location: admin_messages.php?msg=Reply+sent+to+".urlencode($original['email'])."&mtype=ok#msg-$mid"); exit;
    } else {
        header("Location: admin_messages.php?msg=".urlencode('Failed to send: '.$result['error'])."&mtype=err#msg-$mid"); exit;
    }
}

// ── DELETE message ──────────────────────────────────────────────
if(isset($_POST['delete_msg'])){
    csrf_check();
    $mid = (int)$_POST['message_id'];
    $conn->query("DELETE FROM contact_messages WHERE message_id=$mid");
    header("Location: admin_messages.php?msg=Message+deleted.&mtype=ok"); exit;
}

// ── MARK ALL AS READ ────────────────────────────────────────────
if(isset($_GET['mark_all_read'])){
    $conn->query("UPDATE contact_messages SET is_read=1");
    header("Location: admin_messages.php"); exit;
}

// ── MARK single as read (when expanded) ─────────────────────────
if(isset($_GET['read'])){
    $mid = (int)$_GET['read'];
    $conn->query("UPDATE contact_messages SET is_read=1 WHERE message_id=$mid");
    header("Location: admin_messages.php#msg-$mid"); exit;
}

$msg   = $msg ?: ($_GET['msg'] ?? '');
// Whitelist mtype — only 'ok' or 'err' may reach the class attribute
$mtype = (($_GET['mtype'] ?? $mtype) === 'err') ? 'err' : 'ok';

// Fetch all messages newest-first
$messages = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$unread   = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Messages | Apex Admin</title>
<link rel="stylesheet" href="../css/style.css?v=10">
<style>
.msg-card {
    background:var(--card);border:1px solid var(--border);border-radius:10px;
    margin-bottom:10px;overflow:hidden;transition:.2s;
}
.msg-card.unread { border-left:3px solid var(--accent); }
.msg-header {
    display:flex;align-items:center;gap:14px;
    padding:16px 20px;cursor:pointer;
    transition:background .15s;
}
.msg-header:hover { background:rgba(200,84,60,.04); }
.msg-avatar {
    width:40px;height:40px;border-radius:50%;
    background:var(--accent);color:#fff;
    font-family:'Oswald',sans-serif;font-size:1rem;font-weight:700;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.msg-avatar.read { background:rgba(150,100,75,.2);color:var(--muted); }
.msg-meta { flex:1;min-width:0; }
.msg-name  { font-weight:700;color:var(--white);font-size:.9rem; }
.msg-email { font-size:.75rem;color:var(--muted);margin-top:1px; }
.msg-subject { font-size:.8rem;font-weight:600;color:var(--white); }
.msg-preview { font-size:.78rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px; }
.msg-time { font-size:.72rem;color:var(--muted);white-space:nowrap;flex-shrink:0;text-align:right; }
.msg-body {
    display:none;padding:0 20px 20px;
    border-top:1px solid var(--border);
}
.msg-body.open { display:block; }
.msg-text {
    background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);
    padding:14px 16px;font-size:.875rem;color:var(--text);line-height:1.8;
    margin:14px 0;white-space:pre-wrap;
}
.reply-box {
    width:100%;background:var(--navy2);border:1px solid var(--border);
    border-radius:var(--radius);padding:12px 14px;
    font-size:.875rem;color:var(--text);line-height:1.7;
    font-family:inherit;resize:vertical;outline:none;
    transition:border-color .18s;
}
.reply-box:focus { border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,84,60,.15); }
.badge-replied {
    display:inline-flex;align-items:center;gap:4px;
    font-size:.58rem;background:rgba(42,155,90,.15);color:#2a9b5a;
    border:1px solid rgba(42,155,90,.3);border-radius:100px;
    padding:1px 8px;font-weight:700;letter-spacing:.5px;
}
.badge-unread {
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--accent);color:#fff;border-radius:100px;
    font-size:.6rem;font-weight:700;padding:2px 7px;margin-left:6px;letter-spacing:.5px;
}
</style>
</head>
<body>
<div class="admin-layout">
  <?php include 'sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <h1>
        CONTACT MESSAGES
        <?php if($unread > 0): ?>
        <span class="badge-unread"><?=$unread?> NEW</span>
        <?php endif; ?>
      </h1>
      <div style="display:flex;gap:10px;align-items:center;">
        <?php if($unread > 0): ?>
        <a href="admin_messages.php?mark_all_read=1" class="btn btn-secondary btn-sm">
          Mark All Read
        </a>
        <?php endif; ?>
        <span style="color:var(--muted);font-size:.875rem;"><?=$messages->num_rows?> message<?=$messages->num_rows!=1?'s':''?></span>
      </div>
    </div>
    <div class="admin-content">

      <?php if($msg): ?>
      <div class="flash flash-<?=$mtype?>" style="margin-bottom:16px;"><?=e($msg)?></div>
      <?php endif; ?>

      <?php if($messages->num_rows === 0): ?>
      <!-- Empty state -->
      <div style="text-align:center;padding:80px 24px;">
        <div style="font-family:'Oswald',sans-serif;font-size:1.1rem;letter-spacing:2px;color:var(--muted);">NO MESSAGES YET</div>
        <p style="color:var(--muted);font-size:.875rem;margin-top:8px;">Messages submitted via the Contact page will appear here.</p>
      </div>

      <?php else:
        $messages->data_seek(0);
        while($m = $messages->fetch_assoc()):
          $is_unread = !$m['is_read'];
          $avatar    = strtoupper(substr($m['name'], 0, 1));
          $ts        = strtotime($m['created_at']);
          $diff      = time() - $ts;
          if     ($diff < 3600)   $time_str = floor($diff/60).' min ago';
          elseif ($diff < 86400)  $time_str = floor($diff/3600).' hour'.($diff<7200?'':'s').' ago';
          elseif ($diff < 604800) $time_str = floor($diff/86400).' day'.($diff<172800?'':'s').' ago';
          else                    $time_str = date('d M Y', $ts);
      ?>
      <div class="msg-card <?=$is_unread?'unread':''?>" id="msg-<?=(int)$m['message_id']?>">

        <!-- Header row (click to expand) -->
        <div class="msg-header" onclick="toggleMsg(<?=(int)$m['message_id']?>, this)">
          <div class="msg-avatar <?=$is_unread?'':'read'?>"><?=$avatar?></div>
          <div class="msg-meta">
            <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:2px;">
              <span class="msg-name"><?=e($m['name'])?></span>
              <?php if($is_unread): ?>
              <span style="font-size:.58rem;background:var(--accent);color:#fff;border-radius:100px;padding:1px 6px;font-weight:700;letter-spacing:.5px;">NEW</span>
              <?php endif; ?>
              <?php if(!empty($m['admin_reply'])): ?>
              <span class="badge-replied">✓ REPLIED</span>
              <?php endif; ?>
            </div>
            <div class="msg-email"><?=e($m['email'])?></div>
            <div style="margin-top:4px;">
              <span class="msg-subject"><?=e($m['subject'])?></span>
              <span style="color:var(--muted);font-size:.75rem;margin-left:6px;">— <?=e(mb_substr($m['message'],0,60)).(mb_strlen($m['message'])>60?'…':'')?></span>
            </div>
          </div>
          <div class="msg-time">
            <?=$time_str?><br>
            <span style="font-size:.65rem;"><?=date('h:i A', $ts)?></span>
          </div>
          <div style="color:var(--muted);font-size:.8rem;margin-left:8px;flex-shrink:0;" id="arrow-<?=(int)$m['message_id']?>">▼</div>
        </div>

        <!-- Expanded body -->
        <div class="msg-body" id="body-<?=(int)$m['message_id']?>">
          <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:14px;">
            <div style="flex:1;min-width:220px;">
              <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:4px;">From</div>
              <div style="font-weight:600;color:var(--white);"><?=e($m['name'])?></div>
              <div style="font-size:.82rem;color:var(--muted);"><?=e($m['email'])?></div>
            </div>
            <div style="flex:1;min-width:160px;">
              <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:4px;">Subject</div>
              <div style="font-weight:600;color:var(--white);"><?=e($m['subject'])?></div>
            </div>
            <div style="flex:1;min-width:160px;">
              <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:4px;">Received</div>
              <div style="color:var(--white);"><?=date('d M Y, h:i A', $ts)?></div>
            </div>
          </div>

          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-top:14px;margin-bottom:4px;">Message</div>
          <div class="msg-text"><?=e($m['message'])?></div>

<?php if(!empty($m['admin_reply'])): ?>
          <!-- Previously sent reply -->
          <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:#2a9b5a;margin-top:14px;margin-bottom:4px;">
            ✓ Your Reply — sent <?=date('d M Y, h:i A', strtotime($m['replied_at']))?>
          </div>
          <div class="msg-text" style="border-left:3px solid #2a9b5a;"><?=e($m['admin_reply'])?></div>
<?php endif; ?>

          <!-- Reply form: type here, sends to customer's email -->
          <form method="POST" class="reply-form" id="reply-form-<?=(int)$m['message_id']?>" style="display:none;margin:14px 0 0;">
            <?=csrf_field()?>
            <input type="hidden" name="send_reply" value="1">
            <input type="hidden" name="message_id" value="<?=(int)$m['message_id']?>">
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
              Reply to <?=e($m['email'])?>
            </div>
            <textarea name="reply_text" class="reply-box" rows="5" required
                      placeholder="Type your reply here... it will be emailed to <?=e($m['email'])?>"></textarea>
            <div style="display:flex;gap:10px;margin-top:10px;">
              <button type="submit" class="btn btn-primary btn-sm" onclick="this.innerHTML='Sending...';this.style.opacity='.7';">
                <i class="fa-solid fa-paper-plane"></i> Send Reply
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReply(<?=(int)$m['message_id']?>)">
                Cancel
              </button>
            </div>
          </form>

          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px;" id="reply-actions-<?=(int)$m['message_id']?>">
            <button type="button" class="btn btn-primary btn-sm" onclick="toggleReply(<?=(int)$m['message_id']?>)">
              <i class="fa-solid fa-reply"></i> <?=!empty($m['admin_reply'])?'Reply Again':'Reply'?>
            </button>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this message? This cannot be undone.')">
              <?=csrf_field()?>
              <input type="hidden" name="delete_msg" value="1">
              <input type="hidden" name="message_id" value="<?=(int)$m['message_id']?>">
              <button type="submit" class="btn btn-sm" style="background:rgba(214,64,64,.1);border:1px solid rgba(214,64,64,.25);color:var(--danger);">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </form>
          </div>
        </div>

      </div>
      <?php endwhile; endif; ?>

    </div>
  </main>
</div>

<script>
// Auto-expand the message linked in the URL hash (e.g. after sending a reply)
window.addEventListener('DOMContentLoaded', () => {
    const match = location.hash.match(/^#msg-(\d+)$/);
    if(match){
        const header = document.querySelector('#msg-' + match[1] + ' .msg-header');
        if(header) toggleMsg(parseInt(match[1]), header);
    }
});

function toggleReply(id){
    const form    = document.getElementById('reply-form-' + id);
    const actions = document.getElementById('reply-actions-' + id);
    const isOpen  = form.style.display !== 'none';
    form.style.display    = isOpen ? 'none' : 'block';
    actions.style.display = isOpen ? 'flex' : 'none';
    if(!isOpen) form.querySelector('textarea').focus();
}

function toggleMsg(id, headerEl){
    const body  = document.getElementById('body-' + id);
    const arrow = document.getElementById('arrow-' + id);
    const card  = headerEl.closest('.msg-card');
    const isOpen = body.classList.contains('open');

    // Close all others
    document.querySelectorAll('.msg-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('[id^="arrow-"]').forEach(a => a.textContent = '▼');

    if(!isOpen){
        body.classList.add('open');
        arrow.textContent = '▲';

        // If unread, mark as read via AJAX-style fetch then remove badge
        if(card.classList.contains('unread')){
            card.classList.remove('unread');
            const avatar = card.querySelector('.msg-avatar');
            if(avatar) avatar.classList.add('read');
            const badge = card.querySelector('[style*="NEW"]');
            if(badge) badge.remove();

            fetch('admin_messages.php?read=' + id)
                .catch(()=>{});
        }
    }
}
</script>
</body>
</html>
