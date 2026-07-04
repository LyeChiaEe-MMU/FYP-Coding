<?php session_start(); require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Terms of Use | Apex</title>
<link rel="stylesheet" href="css/style.css?v=10">
<style>
.policy-wrap{max-width:780px;margin:60px auto;padding:0 24px 80px;}
.policy-wrap h1{font-family:'Oswald',sans-serif;font-size:2rem;letter-spacing:3px;margin-bottom:6px;}
.policy-wrap .updated{font-size:.78rem;color:var(--muted);margin-bottom:36px;}
.policy-wrap h2{font-family:'Oswald',sans-serif;font-size:1.05rem;letter-spacing:2px;color:var(--accent);margin:28px 0 10px;}
.policy-wrap p,.policy-wrap li{font-size:.9rem;color:var(--muted);line-height:1.8;}
.policy-wrap ul{padding-left:20px;margin:8px 0;}
.policy-wrap li{margin-bottom:6px;}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Section for the terms of use content -->
<div class="policy-wrap">
  <h1>TERMS OF <span style="color:var(--accent)">USE</span></h1>
  <p class="updated">Last updated: June 2026</p>

  <h2>1. Acceptance of Terms</h2>
  <p>By accessing and using the Apex Sport online store, you accept and agree to be bound by these Terms of Use. If you do not agree, please do not use this website.</p>

  <h2>2. Use of the Website</h2>
  <ul>
    <li>You must be at least 13 years old to create an account.</li>
    <li>You agree to provide accurate, current, and complete information during registration.</li>
    <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
    <li>You must not use this site for any unlawful purpose or in violation of any applicable laws.</li>
  </ul>

  <h2>3. Products & Pricing</h2>
  <p>All product descriptions, images, and prices are subject to change without notice. We reserve the right to limit quantities or refuse orders at our discretion.</p>

  <h2>4. Orders & Payments</h2>
  <ul>
    <li>Orders are subject to acceptance and availability.</li>
    <li>Prices are displayed in Malaysian Ringgit (RM).</li>
    <li>Payment methods include online banking (FPX), credit/debit card, e-wallet, and cash on delivery.</li>
    <li>This platform is operated as a simulated store for educational/FYP purposes — no real financial transactions are processed.</li>
  </ul>

  <h2>5. Returns & Refunds</h2>
  <p>We accept returns within 14 days of delivery for items in their original condition. Please refer to our returns page for full details.</p>

  <h2>6. Intellectual Property</h2>
  <p>All content on this site — including logos, images, text, and design — is the property of Apex Sport and may not be reproduced without written permission.</p>

  <h2>7. Limitation of Liability</h2>
  <p>Apex Sport shall not be liable for any indirect, incidental, or consequential damages arising from the use of this website or its products.</p>

  <h2>8. Changes to Terms</h2>
  <p>We may update these Terms at any time. Continued use of the site after changes constitutes acceptance of the new Terms.</p>

  <h2>9. Contact</h2>
  <p>For any questions regarding these Terms, please contact us at <a href="contact.php" class="link-accent">contact page</a>.</p>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
