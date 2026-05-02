<?php session_start(); require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Size Guide | Apex Sport</title>
<link rel="stylesheet" href="css/style.css">
<style>
  .info-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy3) 100%);padding:72px 0;border-bottom:1px solid var(--border)}
  .info-hero h1{font-family:'Oswald',sans-serif;font-size:clamp(36px,5vw,64px);letter-spacing:3px;color:var(--white);margin-bottom:14px}
  .info-hero p{color:var(--muted);max-width:520px;line-height:1.75;font-size:1.05rem}
  .info-section{padding:72px 0}
  .info-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:28px;transition:.2s}
  .info-card:hover{border-color:rgba(100,255,218,.4)}
  .info-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
  .info-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
  .eyebrow{font-size:.72rem;letter-spacing:3px;text-transform:uppercase;color:var(--accent);font-weight:600;margin-bottom:12px}
  .sec-h2{font-family:'Oswald',sans-serif;font-size:clamp(22px,3vw,36px);letter-spacing:2px;color:var(--white);margin-bottom:20px}
  .sz-tbl{width:100%;border-collapse:collapse;font-size:.875rem}
  .sz-tbl th{background:var(--accent);color:var(--navy);padding:12px 16px;text-align:center;font-weight:700;font-family:'Oswald',sans-serif;letter-spacing:1px}
  .sz-tbl td{padding:11px 16px;text-align:center;border-bottom:1px solid var(--border);color:var(--text)}
  .sz-tbl tr:hover td{background:rgba(100,255,218,.04);color:var(--white)}
  .sz-tbl tr:nth-child(even) td{background:rgba(17,34,64,.5)}
  @media(max-width:1024px){.info-grid-2{grid-template-columns:1fr}.info-grid-4{grid-template-columns:1fr 1fr}}
  @media(max-width:768px){.info-grid-4{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="info-hero">
  <div class="wrap">
    <div class="breadcrumb"><a href="index.php">Home</a><span class="sep">/</span><span>Size Guide</span></div>
    <h1>SIZE <span style="color:var(--accent)">GUIDE</span></h1>
    <p>Find your perfect fit. All Apex shoes are measured in UK sizing. Use this guide before placing your order.</p>
  </div>
</div>

<!-- How to Measure -->
<section class="info-section" style="background:var(--navy);">
  <div class="wrap">
    <div class="info-grid-2">
      <div>
        <p class="eyebrow">Step-by-Step</p>
        <h2 class="sec-h2">HOW TO MEASURE YOUR FOOT</h2>
        <div style="display:flex;flex-direction:column;gap:20px;">
          <?php foreach([
            ['1','Place your foot on paper','Stand on a flat sheet of paper with your heel against a wall.'],
            ['2','Trace your foot','Draw around your foot keeping the pen vertical.'],
            ['3','Measure the length','Measure from the heel to the longest toe in centimetres.'],
            ['4','Find your size','Match your measurement to a UK size in the table below.'],
            ['5','When in doubt, size up','If between sizes, always go up half a size.'],
          ] as $s): ?>
          <div style="display:flex;gap:16px;align-items:flex-start;">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--accent);color:var(--navy);font-family:'Oswald',sans-serif;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;"><?=$s[0]?></div>
            <div>
              <div style="font-weight:600;color:var(--white);margin-bottom:3px;"><?=htmlspecialchars($s[1])?></div>
              <div style="font-size:.875rem;color:var(--muted);"><?=htmlspecialchars($s[2])?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="info-card">
        <div style="font-family:'Oswald',sans-serif;font-size:1rem;letter-spacing:2px;color:var(--white);margin-bottom:18px;">QUICK TIPS</div>
        <?php foreach([
          ['👣','Measure in the afternoon — feet swell throughout the day and are largest then.'],
          ['🧦','Measure wearing the socks you plan to wear with the shoes.'],
          ['📏','Always measure both feet — most people have one slightly larger than the other.'],
          ['🏃','For running shoes, add 0.5cm to allow space for foot movement.'],
          ['🏀','For basketball shoes, a snug fit is preferred — go true to size.'],
        ] as $t): ?>
        <div style="display:flex;gap:12px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
          <span style="font-size:1.1rem;"><?=$t[0]?></span>
          <span style="font-size:.875rem;color:var(--muted);line-height:1.65;"><?=htmlspecialchars($t[1])?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Men's Table -->
<section class="info-section" style="background:var(--navy2);border-top:1px solid var(--border);">
  <div class="wrap">
    <h2 class="sec-h2">MEN'S SIZE CHART</h2>
    <div style="overflow-x:auto;border-radius:12px;border:1px solid var(--border);overflow:hidden;">
      <table class="sz-tbl">
        <thead><tr><th>UK</th><th>EU</th><th>US</th><th>Foot Length (cm)</th><th>Foot Length (in)</th></tr></thead>
        <tbody>
          <?php foreach([
            ['6','39','7','24.0','9.4'],['6.5','39.5','7.5','24.5','9.6'],['7','40','8','25.0','9.8'],
            ['7.5','40.5','8.5','25.5','10.0'],['8','41','9','26.0','10.2'],['8.5','42','9.5','26.5','10.4'],
            ['9','42.5','10','27.0','10.6'],['9.5','43','10.5','27.5','10.8'],['10','44','11','28.0','11.0'],
            ['10.5','44.5','11.5','28.5','11.2'],['11','45','12','29.0','11.4'],['12','46.5','13','30.0','11.8'],
          ] as $r): ?>
          <tr><?php foreach($r as $c): ?><td><?=htmlspecialchars($c)?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="height:1px;background:var(--border);margin:48px 0;"></div>

    <h2 class="sec-h2">WOMEN'S SIZE CHART</h2>
    <div style="overflow-x:auto;border-radius:12px;border:1px solid var(--border);overflow:hidden;">
      <table class="sz-tbl">
        <thead><tr><th>UK</th><th>EU</th><th>US</th><th>Foot Length (cm)</th><th>Foot Length (in)</th></tr></thead>
        <tbody>
          <?php foreach([
            ['3','36','5','22.0','8.7'],['3.5','36.5','5.5','22.5','8.9'],['4','37','6','23.0','9.1'],
            ['4.5','37.5','6.5','23.5','9.3'],['5','38','7','24.0','9.4'],['5.5','38.5','7.5','24.5','9.6'],
            ['6','39','8','25.0','9.8'],['6.5','40','8.5','25.5','10.0'],['7','40.5','9','26.0','10.2'],
            ['7.5','41','9.5','26.5','10.4'],['8','42','10','27.0','10.6'],
          ] as $r): ?>
          <tr><?php foreach($r as $c): ?><td><?=htmlspecialchars($c)?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="height:1px;background:var(--border);margin:48px 0;"></div>

    <h2 class="sec-h2">FIT BY CATEGORY</h2>
    <div class="info-grid-4">
      <?php foreach([
        ['🏃','Running','Go half a size up. Foot expands during long runs and needs space.'],
        ['🏀','Basketball','True to size or half down for a snug locked-in feel.'],
        ['💪','Training','True to size. A stable platform is essential for heavy lifts.'],
        ['✨','Lifestyle','True to size or half up for all-day comfort.'],
      ] as $c): ?>
      <div class="info-card">
        <div style="font-size:1.8rem;margin-bottom:12px;"><?=$c[0]?></div>
        <div style="font-family:'Oswald',sans-serif;font-size:.95rem;letter-spacing:1px;color:var(--white);margin-bottom:8px;"><?=htmlspecialchars($c[1])?></div>
        <p style="font-size:.82rem;color:var(--muted);margin:0;"><?=htmlspecialchars($c[2])?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--navy);border-top:1px solid var(--border);padding:60px 0;text-align:center;">
  <div class="wrap">
    <h2 style="font-family:'Oswald',sans-serif;font-size:clamp(24px,3vw,40px);letter-spacing:2px;color:var(--white);margin-bottom:14px;">STILL NOT SURE?</h2>
    <p style="color:var(--muted);margin-bottom:28px;">Our team is happy to help you find the right size.</p>
    <a href="contact.php" class="btn btn-primary btn-lg">Contact Us</a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
