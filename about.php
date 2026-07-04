<?php session_start(); require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>About Apex | Apex Sport</title>
<link rel="stylesheet" href="css/style.css?v=10">
<style>
  .info-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy3) 100%);padding:72px 0;border-bottom:1px solid var(--border)}
  .info-hero h1{font-family:'Oswald',sans-serif;font-size:clamp(36px,5vw,64px);letter-spacing:3px;color:var(--white);margin-bottom:14px}
  .info-hero p{color:var(--muted);max-width:520px;line-height:1.75;font-size:1.05rem}
  .info-section{padding:72px 0}
  .info-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px;margin-bottom:20px;transition:.2s}
  .info-card:hover{border-color:rgba(100,255,218,.4)}
  .info-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
  .info-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
  .eyebrow{font-size:.72rem;letter-spacing:3px;text-transform:uppercase;color:var(--accent);font-weight:600;margin-bottom:12px}
  .sec-h2{font-family:'Oswald',sans-serif;font-size:clamp(22px,3vw,36px);letter-spacing:2px;color:var(--white);margin-bottom:16px}
  .sec-p{color:var(--muted);line-height:1.85;margin-bottom:16px}
  .step-num{width:38px;height:38px;border-radius:50%;background:var(--accent);color:var(--navy);font-family:'Oswald',sans-serif;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700}
  .stat-strip{background:var(--navy2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:60px 0}
  @media(max-width:1024px){.info-grid-2{grid-template-columns:1fr}.info-grid-3{grid-template-columns:1fr 1fr}}
  @media(max-width:768px){.info-grid-3{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Section for the page header -->
<div class="info-hero">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>About Apex</span></div>
    <h1>ABOUT <span style="color:var(--accent)">APEX</span></h1>
    <p>Born from a passion for performance. Built for those who refuse to settle.</p>
  </div>
</div>

<!-- Story -->
<section class="info-section" style="background:var(--navy);">
  <div class="wrap">
    <div class="info-grid-2">
      <div>
        <p class="eyebrow">Our Story</p>
        <h2 class="sec-h2">BUILT FROM THE GROUND UP</h2>
        <p class="sec-p">Apex Sport was founded with a single belief — that every athlete, from the weekend warrior to the elite competitor, deserves footwear that works as hard as they do. We refused to accept the compromise between style and performance.</p>
        <p class="sec-p">Starting from a small workshop in Melaka, Malaysia, our team of engineers and designers obsessed over every millimetre of sole, every stitch of mesh, and every gram of foam. The result is a line of shoes that feel like they were made specifically for you.</p>
        <p class="sec-p">Today, Apex Sport is worn by thousands of athletes across Malaysia — but our mission remains the same: to help you reach the top of whatever game you're playing.</p>
      </div>
      <div style="background:linear-gradient(135deg,var(--navy2) 0%,var(--navy3) 100%);border:1px solid var(--border);border-radius:14px;padding:48px 36px;display:flex;flex-direction:column;justify-content:center;gap:28px;min-height:360px;">
        <!-- Brand identity card -->
        <div style="font-family:'Oswald',sans-serif;font-size:3rem;letter-spacing:8px;color:var(--white);">APE<span style="color:var(--accent);">X</span></div>
        <div style="width:40px;height:3px;background:var(--accent);border-radius:2px;"></div>
        <p style="color:var(--muted);font-size:.95rem;line-height:1.75;margin:0;">
          Premium athletic footwear — engineered in Malaysia, built for champions.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:8px;">
          <?php foreach([['2026','Founded'],['Malaysia','Based In']] as $s): ?>
          <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 18px;">
            <div style="font-family:'Oswald',sans-serif;font-size:1.5rem;color:var(--accent);margin-bottom:4px;"><?=htmlspecialchars($s[0])?></div>
            <div style="font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);"><?=htmlspecialchars($s[1])?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Values -->
<section class="info-section" style="background:var(--navy2);border-top:1px solid var(--border);">
  <div class="wrap">
    <div style="text-align:center;margin-bottom:48px;">
      <p class="eyebrow">What We Stand For</p>
      <h2 class="sec-h2" style="margin-bottom:0;">OUR CORE VALUES</h2>
    </div>
    <div class="info-grid-3">
      <?php foreach([
        ['Performance First','Every design decision starts with one question: does this make the shoe perform better? No compromises.'],
        ['Sustainable Future','We are committed to reducing our carbon footprint by sourcing eco-friendly materials and minimising waste.'],
        ['Community Driven','We work with local athletes and coaches to test and refine our products before they ever reach your feet.'],
        ['Innovation Always','Our R&D team never stops. HyperFoam™, FlexGrid™, and AirMesh Pro™ are just the beginning.'],
        ['Fair Pricing','Premium doesn\'t have to mean unaffordable. We cut out middlemen so the savings reach you directly.'],
        ['Winning Mentality','We don\'t make shoes for participation trophies. We make shoes for people who play to win.'],
      ] as $v): ?>
      <div class="info-card">
        <div style="font-family:'Oswald',sans-serif;font-size:1.3rem;letter-spacing:1.5px;color:var(--white);margin-bottom:10px;"><?=htmlspecialchars($v[0])?></div>
        <p style="font-size:.875rem;color:var(--muted);margin:0;"><?=htmlspecialchars($v[1])?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- Team -->
<section class="info-section" style="background:var(--navy);">
  <div class="wrap">
    <div style="text-align:center;margin-bottom:48px;">
      <p class="eyebrow">The People Behind Apex</p>
      <h2 class="sec-h2" style="margin-bottom:0;">MEET THE TEAM</h2>
    </div>
    <div class="info-grid-3">
      <?php foreach([
        ['Lye Chia Ee','Product Developer & Co-Founder','Handles the product and shopping experience, ensuring customers can browse, select, and purchase with ease.'],
        ['Lau Hui Weng','Front-End Developer & Co-Founder','Responsible for the main storefront — shaping the look, feel, and first impression of the Apex website.'],
        ['Joie Poo Hann Ern','Admin Developer & Co-Founder','Oversees the backend admin system, keeping operations running smoothly behind the scenes.'],
      ] as $m): ?>
      <div class="info-card" style="text-align:center;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#0ea5e9);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:var(--navy);font-family:'Oswald',sans-serif;font-weight:700;">
          <?=strtoupper(substr($m[0],0,1))?>
        </div>
        <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:1px;color:var(--white);margin-bottom:4px;"><?=htmlspecialchars($m[0])?></div>
        <div style="font-size:.72rem;color:var(--accent);font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;"><?=htmlspecialchars($m[1])?></div>
        <p style="font-size:.875rem;color:var(--muted);margin:0;"><?=htmlspecialchars($m[2])?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--navy2);border-top:1px solid var(--border);padding:72px 0;text-align:center;">
  <div class="wrap">
    <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(28px,4vw,48px);letter-spacing:2px;color:var(--white);margin-bottom:14px;">READY TO REACH YOUR APEX?</h2>
    <p style="color:var(--muted);margin-bottom:32px;">Browse our full collection and find the pair made for your game.</p>
    <a href="products.php" class="btn btn-primary btn-lg">Shop All Shoes</a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
