<?php
session_start();
require 'db.php';

if (!is_logged()) {
    header("Location: login.php");
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$errors  = [];
$success = false;

// Get user name
$urow = $conn->query("SELECT name FROM users WHERE user_id=$uid")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shoe_name  = trim($_POST['shoe_name']      ?? '');
    $category   = trim($_POST['category']       ?? '');
    $color_pref = trim($_POST['color_pref']     ?? '');
    $description= trim($_POST['description']    ?? '');
    $specs      = trim($_POST['specifications'] ?? '');

    $allowed_cats = ['Running','Basketball','Training','Lifestyle'];

    if (!$shoe_name)                         $errors[] = "Shoe name is required.";
    if (!in_array($category, $allowed_cats)) $errors[] = "Please select a valid category.";
    if (!$color_pref)                        $errors[] = "Colour preference is required.";
    if (strlen($description) < 20)           $errors[] = "Description must be at least 20 characters.";

    // Handle optional image upload
    $ref_image = null;
    if (!empty($_FILES['ref_image']['name'])) {
        $file    = $_FILES['ref_image'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize = 5 * 1024 * 1024;

        if (!in_array($ext, $allowed))    $errors[] = "Image must be JPG, PNG, GIF or WEBP.";
        elseif ($file['size'] > $maxSize) $errors[] = "Image must be under 5MB.";
        else {
            $filename  = 'design_' . $uid . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/uploads/designs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $ref_image = 'uploads/designs/' . $filename;
            } else {
                $errors[] = "Failed to upload image. Check folder permissions.";
            }
        }
    }

    if (empty($errors)) {
        $sn   = $conn->real_escape_string($shoe_name);
        $cat  = $conn->real_escape_string($category);
        $cp   = $conn->real_escape_string($color_pref);
        $desc = $conn->real_escape_string($description);
        $sp   = $conn->real_escape_string($specs);
        $img  = $ref_image ? "'" . $conn->real_escape_string($ref_image) . "'" : "NULL";

        $conn->query("INSERT INTO design_requests (user_id, shoe_name, category, color_pref, description, specifications, ref_image)
                      VALUES ($uid, '$sn', '$cat', '$cp', '$desc', '$sp', $img)");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Design Your Shoe | Apex</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="page-header" style="background:var(--navy2);">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Design Your Shoe</span></div>
    <h1>DESIGN YOUR <span style="color:var(--accent)">APEX</span></h1>
    <p style="color:var(--muted);margin-top:8px;max-width:520px;">
      Have a shoe idea in mind? Submit it here. Our team will review your design and get back to you.
      Inspired by Nike By You and Adidas Mi Adidas.
    </p>
  </div>
</div>

<section class="section" style="padding-top:40px;">
<div class="wrap" style="max-width:760px;">

<?php if ($success): ?>
  <div style="text-align:center;padding:60px 20px;">
    <div style="font-size:3rem;margin-bottom:20px;">✅</div>
    <h2 style="font-family:'Oswald',sans-serif;font-size:1.8rem;letter-spacing:2px;color:var(--white);margin-bottom:12px;">DESIGN SUBMITTED!</h2>
    <p style="color:var(--muted);margin-bottom:28px;max-width:440px;margin-left:auto;margin-right:auto;">
      Thank you! Our team will review your idea and update the status within 3–5 business days.
      You can track your submissions in My Requests.
    </p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="my_requests.php" class="btn btn-primary">View My Requests</a>
      <a href="design_request.php" class="btn btn-outline">Submit Another</a>
    </div>
  </div>

<?php else: ?>

  <?php foreach ($errors as $err): ?>
  <div class="flash flash-err"><?=e($err)?></div>
  <?php endforeach; ?>

  <!-- Info banner -->
  <div style="background:rgba(100,255,218,.06);border:1px solid rgba(100,255,218,.2);border-radius:10px;padding:20px 24px;margin-bottom:32px;display:flex;gap:16px;align-items:flex-start;">
    <div style="font-size:1.5rem;flex-shrink:0;">💡</div>
    <div>
      <div style="font-family:'Oswald',sans-serif;font-size:.95rem;letter-spacing:1px;color:var(--white);margin-bottom:6px;">HOW IT WORKS</div>
      <ol style="color:var(--muted);font-size:.85rem;line-height:2;padding-left:18px;">
        <li>Fill in your shoe design idea below</li>
        <li>Our team reviews your submission (3–5 business days)</li>
        <li>You'll be notified with Approved / In Review / Rejected status</li>
        <li>Approved designs may be developed into real Apex products!</li>
      </ol>
    </div>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <div class="card" style="padding:32px;">

      <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);margin-bottom:24px;padding-bottom:14px;border-bottom:1px solid var(--border);">
        YOUR DESIGN DETAILS
      </div>

      <!-- Shoe name -->
      <div class="form-group">
        <label>Shoe Name *</label>
        <input type="text" name="shoe_name" placeholder="e.g. Apex Thunder Bolt"
               value="<?=e($_POST['shoe_name']??'')?>" required
               style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;">
      </div>

      <!-- Category -->
      <div class="form-group">
        <label>Category *</label>
        <select name="category" required
                style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;">
          <option value="">-- Select Category --</option>
          <?php foreach(['Running','Basketball','Training','Lifestyle'] as $cat):
            $sel = ($_POST['category']??'')===$cat?'selected':'';
          ?>
          <option value="<?=$cat?>" <?=$sel?>><?=$cat?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Colour preference -->
      <div class="form-group">
        <label>Colour Preference *</label>
        <input type="text" name="color_pref"
               placeholder="e.g. Matte black upper with neon green sole and white laces"
               value="<?=e($_POST['color_pref']??'')?>" required
               style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;">
      </div>

      <!-- Description -->
      <div class="form-group">
        <label>Design Description * <span style="color:var(--muted);font-size:.75rem;font-weight:400;">(min. 20 characters)</span></label>
        <textarea name="description" rows="5" required
                  placeholder="Describe your shoe design in detail. What makes it unique? What problem does it solve? What's the inspiration?"
                  style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;resize:vertical;"><?=e($_POST['description']??'')?></textarea>
        <div id="descCount" style="font-size:.72rem;color:var(--muted);text-align:right;margin-top:4px;">0 characters</div>
      </div>

      <!-- Specifications -->
      <div class="form-group">
        <label>Specifications <span style="color:var(--muted);font-size:.75rem;font-weight:400;">(optional)</span></label>
        <textarea name="specifications" rows="3"
                  placeholder="e.g. Flyknit mesh upper, carbon fibre sole plate, memory foam insole, waterproof coating..."
                  style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;resize:vertical;"><?=e($_POST['specifications']??'')?></textarea>
      </div>

      <!-- Reference image -->
      <div class="form-group">
        <label>Reference Image <span style="color:var(--muted);font-size:.75rem;font-weight:400;">(optional — max 5MB, JPG/PNG/GIF/WEBP)</span></label>
        <input type="file" name="ref_image" accept=".jpg,.jpeg,.png,.gif,.webp"
               style="width:100%;background:var(--navy2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;color:var(--white);font-size:.9rem;">
        <div style="font-size:.75rem;color:var(--muted);margin-top:6px;">Upload a sketch, reference photo or mood board to help us understand your vision.</div>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;font-size:1.05rem;">
        SUBMIT MY DESIGN IDEA →
      </button>
    </div>
  </form>

<?php endif; ?>
</div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
// Live character counter
const desc = document.querySelector('textarea[name="description"]');
const cnt  = document.getElementById('descCount');
if (desc && cnt) {
    desc.addEventListener('input', () => {
        const len = desc.value.length;
        cnt.textContent = len + ' characters';
        cnt.style.color = len >= 20 ? 'var(--accent)' : 'var(--muted)';
    });
}
</script>
