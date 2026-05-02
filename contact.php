<?php
session_start();
require 'db.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name)                                     $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    if (!$subject)                                  $errors[] = "Please select a subject.";
    if (strlen($message) < 10)                      $errors[] = "Message must be at least 10 characters.";

    if (empty($errors)) $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Contact Us | Apex Sport</title>
<link rel="stylesheet" href="css/style.css">
<style>
  .info-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy3) 100%);padding:72px 0;border-bottom:1px solid var(--border)}
  .info-hero h1{font-family:'Oswald',sans-serif;font-size:clamp(36px,5vw,64px);letter-spacing:3px;color:var(--white);margin-bottom:14px}
  .info-hero p{color:var(--muted);max-width:520px;line-height:1.75;font-size:1.05rem}
  .info-section{padding:72px 0}
  .info-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
  .eyebrow{font-size:.72rem;letter-spacing:3px;text-transform:uppercase;color:var(--accent);font-weight:600;margin-bottom:12px}
  .sec-h2{font-family:'Oswald',sans-serif;font-size:clamp(22px,3vw,36px);letter-spacing:2px;color:var(--white);margin-bottom:20px}
  .ci-block{display:flex;gap:14px;align-items:flex-start;margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--border)}
  .ci-block:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
  .contact-form{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px}
  .faq-item{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:8px;overflow:hidden}
  .faq-btn{display:flex;justify-content:space-between;align-items:center;width:100%;background:var(--card);border:none;padding:16px 20px;font-size:.9rem;font-weight:600;color:var(--text);cursor:pointer;text-align:left;gap:12px;transition:.2s}
  .faq-btn:hover{color:var(--white)}
  .faq-body{display:none;padding:0 20px 16px;color:var(--muted);font-size:.875rem;line-height:1.75;border-top:1px solid var(--border)}
  @media(max-width:1024px){.info-grid-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="info-hero">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Contact Us</span></div>
    <h1>CONTACT <span style="color:var(--accent)">US</span></h1>
    <p>Have a question, feedback, or need help with your order? We'd love to hear from you.</p>
  </div>
</div>

<!-- Contact Main -->
<section class="info-section" style="background:var(--navy);">
  <div class="wrap">
    <div class="info-grid-2">

      <!-- Info Column -->
      <div>
        <p class="eyebrow">Get In Touch</p>
        <h2 class="sec-h2">WE'RE HERE TO HELP</h2>
        <p style="color:var(--muted);margin-bottom:36px;">Our support team is available Monday to Friday, 9:00 AM – 6:00 PM (MYT). We aim to respond within 1 business day.</p>

        <?php foreach([
          ['📍','Our Address','Multimedia University<br>Jalan Multimedia, 63100<br>Cyberjaya, Selangor, Malaysia'],
          ['📧','Email Us','support@apexsport.my<br><span style="font-size:.78rem;color:var(--muted)">Response within 1 business day</span>'],
          ['📞','Call Us','+60 11-3190 8939<br><span style="font-size:.78rem;color:var(--muted)">Mon – Fri, 9:00 AM – 6:00 PM</span>'],
          ['💬','WhatsApp','+60 11-3190 8939<br><span style="font-size:.78rem;color:var(--muted)">Quick replies during office hours</span>'],
        ] as $ci): ?>
        <div class="ci-block">
          <div style="font-size:1.5rem;flex-shrink:0;margin-top:2px;"><?=$ci[0]?></div>
          <div>
            <div style="font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:5px;font-weight:600;"><?=htmlspecialchars($ci[1])?></div>
            <div style="font-size:.95rem;color:var(--white);line-height:1.6;"><?=$ci[2]?></div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Social -->
        <div style="margin-top:28px;padding-top:24px;border-top:1px solid var(--border);">
          <div style="font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:14px;">Follow Us</div>
          <div style="display:flex;gap:10px;">
            <?php foreach([['📸','Instagram'],['🐦','Twitter'],['📘','Facebook']] as $s): ?>
            <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;text-align:center;min-width:84px;transition:.2s;" onmouseover="this.style.borderColor='rgba(100,255,218,.4)'" onmouseout="this.style.borderColor='var(--border)'">
              <div style="font-size:1.2rem;margin-bottom:4px;"><?=$s[0]?></div>
              <div style="font-size:.72rem;color:var(--muted);"><?=htmlspecialchars($s[1])?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Form Column -->
      <div class="contact-form">
        <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border);">SEND US A MESSAGE</div>

        <?php if ($success): ?>
        <div class="flash flash-ok">✅ Your message has been sent! We'll reply within 1 business day.</div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
        <div class="flash flash-err"><?=htmlspecialchars($err)?></div>
        <?php endforeach; ?>

        <?php if (!$success): ?>
        <form method="POST">
          <div class="form-row">
            <div class="form-group">
              <label>Your Name *</label>
              <input type="text" name="name" placeholder="Full name"
                     value="<?=htmlspecialchars($_POST['name'] ?? (is_logged() ? $_SESSION['user_name'] ?? '' : ''))?>" required>
            </div>
            <div class="form-group">
              <label>Email Address *</label>
              <input type="email" name="email" placeholder="you@email.com"
                     value="<?=htmlspecialchars($_POST['email'] ?? '')?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Subject *</label>
            <select name="subject" required>
              <option value="">-- Select a topic --</option>
              <?php foreach(['Order Enquiry','Return / Exchange','Size Help','Product Question','Shipping Issue','Feedback','Other'] as $sub):
                $sel = ($_POST['subject'] ?? '') === $sub ? 'selected' : '';
              ?>
              <option value="<?=htmlspecialchars($sub)?>" <?=$sel?>><?=htmlspecialchars($sub)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Message *</label>
            <textarea name="message" rows="5" placeholder="Tell us how we can help..."><?=htmlspecialchars($_POST['message'] ?? '')?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-full">SEND MESSAGE →</button>
          <p style="font-size:.75rem;color:var(--muted);text-align:center;margin-top:12px;">We'll reply to your email within 1 business day.</p>
        </form>
        <?php else: ?>
        <div style="text-align:center;padding:20px 0;">
          <a href="contact.php" class="btn btn-outline">Send Another Message</a>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<!-- FAQ -->
<section class="info-section" style="background:var(--navy2);border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="text-align:center;margin-bottom:40px;">
      <p class="eyebrow">Before You Contact Us</p>
      <h2 class="sec-h2" style="margin-bottom:0;">FREQUENTLY ASKED QUESTIONS</h2>
    </div>
    <div style="max-width:780px;margin:0 auto;">
      <?php foreach([
        ['How do I track my order?','Log in to your account and go to "My Orders". You will see the current status. When marked as "Shipped", an estimated delivery date is shown.'],
        ['Can I change or cancel my order?','Orders can be cancelled within 1 hour of placement by contacting us via WhatsApp. Once packed, it can no longer be cancelled — but you can return it after delivery.'],
        ['How long does delivery take?','Standard delivery takes 2–4 business days for Peninsular Malaysia and 5–7 business days for Sabah and Sarawak.'],
        ['My size is out of stock — when will it be restocked?','Restock timelines vary. Contact us with the product name and size and we will notify you as soon as it is available.'],
        ['Do you ship internationally?','Currently we only ship within Malaysia. International shipping is planned for a future update.'],
        ['What if I receive the wrong item?','Contact us immediately with a photo of the item and your Order ID. We will arrange a free replacement.'],
      ] as $faq): ?>
      <div class="faq-item">
        <button class="faq-btn" onclick="toggleFaq(this)">
          <?=htmlspecialchars($faq[0])?>
          <span class="faq-ico" style="font-size:1.2rem;flex-shrink:0;color:var(--muted);transition:transform .2s;">+</span>
        </button>
        <div class="faq-body"><div style="padding-top:14px;"><?=htmlspecialchars($faq[1])?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:32px;">
      <p style="color:var(--muted);font-size:.875rem;margin-bottom:16px;">Can't find what you're looking for?</p>
      <a href="mailto:support@apexsport.my" class="btn btn-primary">Email Us Directly</a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
function toggleFaq(btn) {
    const body = btn.nextElementSibling;
    const ico  = btn.querySelector('.faq-ico');
    const open = body.style.display === 'block';
    document.querySelectorAll('.faq-body').forEach(b => b.style.display = 'none');
    document.querySelectorAll('.faq-ico').forEach(i => { i.textContent = '+'; i.style.transform = 'none'; });
    if (!open) { body.style.display = 'block'; ico.textContent = '−'; ico.style.transform = 'rotate(180deg)'; }
}
</script>
</body>
</html>
