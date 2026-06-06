<?php $depth = (strpos($_SERVER['PHP_SELF'],'/admin/')!==false)?'../':''; ?>
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <div class="footer-brand-logo" style="margin-bottom:12px;">APE<span>X</span></div>
        <p style="color:var(--muted);font-size:.875rem;line-height:1.7;">Premium athletic footwear engineered for performance. Every step forward starts with Apex.</p>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Product</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>products.php?cat=Running" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Running</a></li>
          <li><a href="<?=$depth?>products.php?cat=Basketball" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Basketball</a></li>
          <li><a href="<?=$depth?>products.php?cat=Training" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Training</a></li>
          <li><a href="<?=$depth?>products.php?cat=Lifestyle" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Lifestyle</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Account</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>profile.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">My Profile</a></li>
          <li><a href="<?=$depth?>login.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Login</a></li>
          <li><a href="<?=$depth?>register.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Register</a></li>
          <li><a href="<?=$depth?>order_history.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">My Orders</a></li>
          <li><a href="<?=$depth?>design_request.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Design Your Shoe</a></li>
          <li><a href="<?=$depth?>my_requests.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">My Requests</a></li>
          <li><a href="<?=$depth?>cart.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Cart</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 style="font-size:.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--white);margin-bottom:16px;font-weight:700;">Discover</h4>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
          <li><a href="<?=$depth?>leaderboard.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Leaderboard</a></li>
          <li><a href="<?=$depth?>about.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">About Apex</a></li>
          <li><a href="<?=$depth?>size_guide.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Size Guide</a></li>
          <li><a href="<?=$depth?>contact.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Contact Us</a></li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div style="margin-top:40px;padding-top:20px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center;">
      <div style="display:flex;gap:24px;flex-wrap:wrap;justify-content:center;">
        <a href="<?=$depth?>returns.php" style="font-size:.8rem;color:var(--muted);transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Returns Policy</a>
        <a href="<?=$depth?>size_guide.php" style="font-size:.8rem;color:var(--muted);transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Size Guide</a>
        <a href="<?=$depth?>contact.php" style="font-size:.8rem;color:var(--muted);transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Contact Us</a>
      </div>
      <div style="display:flex;justify-content:space-between;width:100%;flex-wrap:wrap;gap:8px;font-size:.8rem;color:var(--muted);">
        <span>© <?=date('Y')?> Apex Sport. All rights reserved.</span>
        <span>MMU FYP — TFP4224</span>
      </div>
    </div>
  </div>
</footer>
</body></html>
