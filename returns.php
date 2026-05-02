<?php session_start(); require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Returns Policy | Apex Sport</title>
<link rel="stylesheet" href="css/style.css">
<style>
  .info-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy3) 100%);padding:72px 0;border-bottom:1px solid var(--border)}
  .info-hero h1{font-family:'Oswald',sans-serif;font-size:clamp(36px,5vw,64px);letter-spacing:3px;color:var(--white);margin-bottom:14px}
  .info-hero p{color:var(--muted);max-width:520px;line-height:1.75;font-size:1.05rem}
  .info-section{padding:72px 0}
  .info-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px;margin-bottom:20px;transition:.2s}
  .info-card:hover{border-color:rgba(100,255,218,.3)}
  .eyebrow{font-size:.72rem;letter-spacing:3px;text-transform:uppercase;color:var(--accent);font-weight:600;margin-bottom:12px}
  .sec-h2{font-family:'Oswald',sans-serif;font-size:clamp(20px,2.5vw,30px);letter-spacing:2px;color:var(--white);margin-bottom:16px}
  .ref-tbl{width:100%;border-collapse:collapse;font-size:.875rem}
  .ref-tbl th{background:var(--accent);color:var(--navy);padding:12px 16px;text-align:left;font-weight:700;font-family:'Oswald',sans-serif;letter-spacing:1px}
  .ref-tbl td{padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text)}
  .ref-tbl tr:hover td{background:rgba(100,255,218,.04)}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="info-hero">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Returns Policy</span></div>
    <h1>RETURNS <span style="color:var(--accent)">POLICY</span></h1>
    <p>We want you to love every pair. If something isn't right, we make it easy to return or exchange.</p>
  </div>
</div>

<!-- Key highlights -->
<section style="background:var(--navy2);border-bottom:1px solid var(--border);padding:48px 0;">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;text-align:center;">
      <?php foreach([
        ['↩️','30-Day Returns','Return any unworn pair within 30 days.'],
        ['🔄','Free Exchanges','Swap for a different size at no cost.'],
        ['📦','Easy Process','Email us and we guide you through every step.'],
        ['💳','Full Refund','Refunded to your original payment method.'],
      ] as $k): ?>
      <div>
        <div style="font-size:2rem;margin-bottom:10px;"><?=$k[0]?></div>
        <div style="font-family:'Oswald',sans-serif;font-size:.9rem;letter-spacing:1px;color:var(--white);margin-bottom:6px;"><?=htmlspecialchars($k[1])?></div>
        <div style="font-size:.8rem;color:var(--muted);"><?=htmlspecialchars($k[2])?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Policy Details -->
<section class="info-section" style="background:var(--navy);">
  <div class="wrap" style="max-width:860px;">

    <div class="info-card">
      <h2 class="sec-h2">RETURN ELIGIBILITY</h2>
      <p style="color:var(--muted);margin-bottom:14px;">To be eligible for a return or exchange, your item must meet all of the following:</p>
      <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;">
        <?php foreach([
          'Returned within <strong style="color:var(--white)">30 days</strong> of the delivery date',
          'Shoes must be <strong style="color:var(--white)">unworn and unused</strong> with no signs of wear',
          'In their <strong style="color:var(--white)">original packaging</strong> with all tags attached',
          'Valid <strong style="color:var(--white)">Order ID</strong> must be provided',
          'Not <strong style="color:var(--white)">customised or altered</strong> in any way',
        ] as $item): ?>
        <li style="display:flex;gap:10px;align-items:flex-start;font-size:.9rem;color:var(--muted);">
          <span style="color:var(--accent);font-size:1rem;flex-shrink:0;margin-top:1px;">✓</span>
          <span><?=$item?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="info-card">
      <h2 class="sec-h2">NON-RETURNABLE ITEMS</h2>
      <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;">
        <?php foreach([
          'Shoes that have been worn outdoors or show signs of use',
          'Items marked as "Final Sale" or purchased at clearance prices',
          'Gift cards and vouchers',
          'Items returned after the 30-day window',
          'Items without original packaging or missing tags',
        ] as $item): ?>
        <li style="display:flex;gap:10px;align-items:flex-start;font-size:.9rem;color:var(--muted);">
          <span style="color:var(--danger);flex-shrink:0;">✕</span>
          <?=htmlspecialchars($item)?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="info-card">
      <h2 class="sec-h2">HOW TO RETURN — STEP BY STEP</h2>
      <div style="display:flex;flex-direction:column;gap:0;">
        <?php foreach([
          ['1','Email us at returns@apexsport.my','Include your Order ID, reason for return, and whether you want a refund or exchange.'],
          ['2','Pack your shoes securely','Use the original box with all accessories and tags. Use a protective outer bag.'],
          ['3','Ship your return','Drop at any J&T Express or Pos Laju outlet. Keep your tracking receipt.'],
          ['4','We inspect your return','Inspection takes 2–3 business days. We notify you by email of the outcome.'],
          ['5','Receive your refund or exchange','Refunds processed in 5–7 business days. Exchanges ship within 2 business days.'],
        ] as $step): ?>
        <div style="display:flex;gap:16px;align-items:flex-start;padding:20px 0;border-bottom:1px solid var(--border);">
          <div style="width:38px;height:38px;border-radius:50%;background:var(--accent);color:var(--navy);font-family:'Oswald',sans-serif;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;"><?=$step[0]?></div>
          <div>
            <div style="font-weight:600;color:var(--white);margin-bottom:5px;"><?=htmlspecialchars($step[1])?></div>
            <div style="font-size:.875rem;color:var(--muted);line-height:1.65;"><?=htmlspecialchars($step[2])?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="info-card">
      <h2 class="sec-h2">REFUND TIMELINE</h2>
      <div style="overflow-x:auto;border-radius:8px;overflow:hidden;border:1px solid var(--border);">
        <table class="ref-tbl">
          <thead><tr><th>Payment Method</th><th>Processing Time</th><th>Total Time</th></tr></thead>
          <tbody>
            <?php foreach([
              ['Online Banking (FPX)','3–5 business days','5–7 business days'],
              ['Credit / Debit Card','5–7 business days','7–10 business days'],
              ['GrabPay','1–3 business days','3–5 business days'],
              ['Touch \'n Go','1–3 business days','3–5 business days'],
              ['Cash on Delivery','3–5 business days','Store credit within 5 days'],
            ] as $r): ?>
            <tr><?php foreach($r as $c): ?><td><?=htmlspecialchars($c)?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="info-card" style="border-color:rgba(100,255,218,.3);background:rgba(100,255,218,.04);">
      <h2 class="sec-h2" style="color:var(--accent);">EXCHANGES</h2>
      <p style="color:var(--muted);margin-bottom:10px;">Need a different size or colour? Exchanges are free. Follow the return steps and note that you want an exchange in your email. We ship the replacement as soon as we receive and inspect your return.</p>
      <p style="color:var(--muted);margin:0;">If the requested size or style is out of stock, we issue a full refund and notify you immediately.</p>
    </div>

  </div>
</section>

<!-- CTA -->
<section style="background:var(--navy2);border-top:1px solid var(--border);padding:60px 0;text-align:center;">
  <div class="wrap">
    <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,3vw,40px);letter-spacing:2px;color:var(--white);margin-bottom:14px;">QUESTIONS ABOUT YOUR RETURN?</h2>
    <p style="color:var(--muted);margin-bottom:28px;">Our support team is available Monday–Friday, 9am–6pm.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="contact.php" class="btn btn-primary btn-lg">Contact Support</a>
      <a href="order_history.php" class="btn btn-outline btn-lg">View My Orders</a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
