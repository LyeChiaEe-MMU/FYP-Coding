<?php $depth = (strpos($_SERVER['PHP_SELF'],'/admin/')!==false)?'../':''; ?>
<footer class="footer" style="background:#0d1f35;border-top:1px solid rgba(100,255,218,.15);">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <div class="footer-brand-logo" style="margin-bottom:12px;">APE<span>X</span></div>
        <p style="color:#a8b8cc;font-size:.875rem;line-height:1.7;">Premium athletic footwear engineered for performance. Every step forward starts with Apex.</p>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Product</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>products.php?cat=Running" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Running</a></li>
          <li><a href="<?=$depth?>products.php?cat=Basketball" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Basketball</a></li>
          <li><a href="<?=$depth?>products.php?cat=Training" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Training</a></li>
          <li><a href="<?=$depth?>products.php?cat=Lifestyle" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Lifestyle</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Account</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>login.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Login</a></li>
          <li><a href="<?=$depth?>register.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Register</a></li>
          <li><a href="<?=$depth?>order_history.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">My Orders</a></li>
          <li><a href="<?=$depth?>design_request.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Design Your Shoe</a></li>
          <li><a href="<?=$depth?>my_requests.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">My Requests</a></li>
          <li><a href="<?=$depth?>cart.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Cart</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Discover</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>leaderboard.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Leaderboard</a></li>
          <li><a href="<?=$depth?>about.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">About Apex</a></li>
          <li><a href="<?=$depth?>size_guide.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Size Guide</a></li>
          <li><a href="<?=$depth?>contact.php" style="color:#a8b8cc;font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Contact Us</a></li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center;">
      <div style="display:flex;gap:24px;flex-wrap:wrap;justify-content:center;">
        <a href="<?=$depth?>returns.php" style="font-size:.8rem;color:#a8b8cc;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Returns Policy</a>
        <a href="<?=$depth?>size_guide.php" style="font-size:.8rem;color:#a8b8cc;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Size Guide</a>
        <a href="<?=$depth?>contact.php" style="font-size:.8rem;color:#a8b8cc;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#a8b8cc'">Contact Us</a>
      </div>
      <div style="display:flex;justify-content:space-between;width:100%;flex-wrap:wrap;gap:8px;font-size:.8rem;color:#7a8fa3;">
        <span>© <?=date('Y')?> Apex Sport. All rights reserved.</span>
        <span>MMU FYP — TFP4224</span>
      </div>
    </div>
  </div>
</footer>
</body></html>
