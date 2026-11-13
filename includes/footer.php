  </main>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <a class="brand brand-light" href="<?= e(url('index.php')) ?>">
          <span class="brand-mark">TH</span>
          <span class="brand-name"><?= e(setting('site_name', 'TradeHub')) ?></span>
        </a>
        <p class="footer-copy"><?= e(setting('tagline')) ?></p>
      </div>
      <div>
        <h4>Marketplace</h4>
        <ul>
          <li><a href="<?= e(url('products.php')) ?>">Browse products</a></li>
          <li><a href="<?= e(url('products.php?available=1')) ?>">In stock today</a></li>
          <li><a href="<?= e(url('cart.php')) ?>">Your cart</a></li>
        </ul>
      </div>
      <div>
        <h4>Account</h4>
        <ul>
          <?php if (current_user()): ?>
            <li><a href="<?= e(url('profile.php')) ?>">Profile</a></li>
            <li><a href="<?= e(url('orders.php')) ?>">Order history</a></li>
          <?php else: ?>
            <li><a href="<?= e(url('login.php')) ?>">Log in</a></li>
            <li><a href="<?= e(url('register.php')) ?>">Create account</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul>
          <li><?= e(setting('contact_email')) ?></li>
          <li><?= e(setting('contact_phone')) ?></li>
          <li>Mon–Sat, 8:00 AM – 6:00 PM</li>
        </ul>
      </div>
    </div>
    <div class="container footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'TradeHub')) ?>. Supporting local sellers.</span>
      <span>Commission-based marketplace</span>
    </div>
  </footer>
  <script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
</body>
</html>
