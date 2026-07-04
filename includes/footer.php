<?php $depth = (strpos($_SERVER['PHP_SELF'],'/admin/')!==false)?'../':''; ?>
<!-- Section for the site footer -->
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
          <li><a href="<?=$depth?>notifications.php" style="color:var(--muted);font-size:.875rem;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Notifications</a></li>
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

<?php if($depth === ''): // customer pages only, not admin ?>
<!-- ── Floating WhatsApp chat button ── -->
<style>
.wa-float{
  position:fixed;bottom:24px;right:24px;z-index:9990;
  width:56px;height:56px;border-radius:50%;
  background:#25D366;box-shadow:0 6px 20px rgba(37,211,102,.45);
  display:flex;align-items:center;justify-content:center;
  transition:transform .2s, box-shadow .2s;
}
.wa-float:hover{transform:scale(1.1);box-shadow:0 8px 26px rgba(37,211,102,.6);}
.wa-float svg{width:30px;height:30px;fill:#fff;}
.wa-tip{
  position:fixed;bottom:36px;right:90px;z-index:9990;
  background:var(--card,#fff);border:1px solid var(--border,#ddd);
  color:var(--text,#333);font-size:.75rem;font-weight:600;
  padding:7px 14px;border-radius:100px;white-space:nowrap;
  box-shadow:0 4px 14px rgba(0,0,0,.15);
  opacity:0;pointer-events:none;transform:translateX(8px);
  transition:opacity .2s, transform .2s;
}
.wa-float:hover + .wa-tip, .wa-float:focus + .wa-tip{opacity:1;transform:translateX(0);}
@media(max-width:640px){ .wa-float{bottom:18px;right:18px;width:50px;height:50px;} }
</style>
<a href="https://wa.me/601131908939?text=<?=urlencode('Hi Apex Store! I need help with ')?>"
   class="wa-float" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
  <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path d="M16.004 3C9.383 3 4 8.383 4 15.004c0 2.647.867 5.098 2.332 7.086L4.06 28.94l7.05-2.238a11.94 11.94 0 0 0 4.895 1.048h.005C22.63 27.75 28 22.367 28 15.746 28 8.383 22.625 3 16.004 3zm0 22.5c-1.767 0-3.42-.474-4.844-1.3l-.348-.206-3.598 1.142 1.164-3.484-.227-.36a9.93 9.93 0 0 1-1.559-5.288c0-5.514 4.486-10 10-10 5.516 0 9.912 4.486 9.912 10 0 5.516-4.984 9.496-10.5 9.496zm5.482-7.44c-.301-.15-1.781-.878-2.057-.979-.276-.1-.477-.15-.678.152-.2.3-.777.977-.953 1.178-.176.2-.352.226-.653.075-.301-.15-1.271-.468-2.42-1.492-.895-.798-1.5-1.784-1.676-2.085-.176-.301-.019-.464.132-.613.135-.135.301-.352.452-.528.15-.176.2-.301.301-.502.1-.2.05-.377-.025-.527-.076-.15-.678-1.633-.928-2.235-.244-.588-.493-.508-.678-.518l-.577-.01c-.2 0-.527.075-.803.377-.276.3-1.054 1.03-1.054 2.512s1.079 2.914 1.229 3.115c.15.2 2.123 3.24 5.144 4.545.719.31 1.28.495 1.717.634.722.229 1.379.197 1.898.12.579-.087 1.781-.728 2.032-1.431.251-.704.251-1.307.176-1.432-.075-.125-.276-.2-.577-.351z"/></svg>
</a>
<span class="wa-tip">Chat with us on WhatsApp</span>
<?php endif; ?>
</body></html>
