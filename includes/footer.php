<?php $depth = (strpos($_SERVER['PHP_SELF'],'/admin/')!==false)?'../':''; ?>
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <div class="footer-brand-logo">APE<span>X</span></div>
        <p>Premium athletic footwear engineered for performance. Every step forward starts with Apex.</p>
      </div>
      <div class="footer-col">
        <h4>Shop</h4>
        <ul>
          <li><a href="<?=$depth?>products.php?cat=Running">Running</a></li>
          <li><a href="<?=$depth?>products.php?cat=Basketball">Basketball</a></li>
          <li><a href="<?=$depth?>products.php?cat=Training">Training</a></li>
          <li><a href="<?=$depth?>products.php?cat=Lifestyle">Lifestyle</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Account</h4>
        <ul>
          <li><a href="<?=$depth?>login.php">Login</a></li>
          <li><a href="<?=$depth?>register.php">Register</a></li>
          <li><a href="<?=$depth?>order_history.php">My Orders</a></li>
          <li><a href="<?=$depth?>design_request.php">Design Your Shoe</a></li>
          <li><a href="<?=$depth?>my_requests.php">My Requests</a></li>
          <li><a href="<?=$depth?>cart.php">Cart</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Discover</h4>
        <ul>
          <li><a href="<?=$depth?>leaderboard.php">🏆 Leaderboard</a></li>
          <li><a href="<?=$depth?>about.php">About Apex</a></li>
          <li><a href="<?=$depth?>size_guide.php">Size Guide</a></li>
          <li><a href="<?=$depth?>returns.php">Returns Policy</a></li>
          <li><a href="<?=$depth?>contact.php">Contact Us</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?=date('Y')?> Apex Sport. All rights reserved.</span>
      <span>MMU FYP — TFP4224</span>
    </div>
  </div>
</footer>
</body></html>
