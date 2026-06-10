<?php session_start(); require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Privacy Policy | Apex</title>
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

<div class="policy-wrap">
  <h1>PRIVACY <span style="color:var(--accent)">POLICY</span></h1>
  <p class="updated">Last updated: June 2026</p>

  <h2>1. Information We Collect</h2>
  <ul>
    <li><strong style="color:var(--white)">Account information:</strong> Name, email address, phone number, date of birth, and shipping address provided during registration.</li>
    <li><strong style="color:var(--white)">Order information:</strong> Products purchased, sizes, quantities, and payment method selected.</li>
    <li><strong style="color:var(--white)">Usage data:</strong> Pages visited, search queries, and wishlist activity within the site.</li>
  </ul>

  <h2>2. How We Use Your Information</h2>
  <ul>
    <li>To process and fulfil your orders.</li>
    <li>To pre-fill your shipping details for a faster checkout experience.</li>
    <li>To send notifications about price drops on wishlisted items.</li>
    <li>To personalise product recommendations based on your shopping preference.</li>
    <li>To improve our website and customer experience.</li>
  </ul>

  <h2>3. Data Sharing</h2>
  <p>We do not sell, trade, or rent your personal information to third parties. Data is only shared with service providers strictly necessary to operate the store (e.g., payment processors).</p>

  <h2>4. Cookies</h2>
  <p>We use session cookies to maintain your login state and cart. No third-party tracking cookies are used. You can disable cookies in your browser settings, but this may affect site functionality.</p>

  <h2>5. Data Security</h2>
  <p>Your password is stored as a secure hash (bcrypt). We take reasonable technical measures to protect your personal information from unauthorised access.</p>

  <h2>6. Data Retention</h2>
  <p>Your account data is retained as long as your account is active. You may request deletion of your account and associated data by contacting us.</p>

  <h2>7. Your Rights</h2>
  <ul>
    <li>Access the personal data we hold about you.</li>
    <li>Request correction of inaccurate data.</li>
    <li>Request deletion of your account.</li>
    <li>Withdraw consent for marketing communications at any time.</li>
  </ul>

  <h2>8. Changes to This Policy</h2>
  <p>We may update this Privacy Policy from time to time. We will notify you of significant changes via the email address on your account.</p>

  <h2>9. Contact</h2>
  <p>For privacy-related enquiries, please reach us via the <a href="contact.php" class="link-accent">contact page</a>.</p>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
