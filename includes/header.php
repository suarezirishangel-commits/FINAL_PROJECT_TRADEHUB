<?php
if (!defined('APP_BASE')) {
    require __DIR__ . '/bootstrap.php';
}

$pageTitle  = $pageTitle ?? setting('site_name', 'TradeHub');
$active     = $active ?? '';
$user       = current_user();
$cartCount  = array_sum($_SESSION['cart'] ?? []);
$siteName   = setting('site_name', 'TradeHub');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
<meta name="description" content="<?= e(setting('tagline')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-mark">TH</span>
      <span class="brand-name"><?= e($siteName) ?><small>Local marketplace</small></span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="Main navigation">
      <a href="<?= e(url('index.php')) ?>" class="<?= $active === 'home' ? 'is-active' : '' ?>">Home</a>
      <a href="<?= e(url('products.php')) ?>" class="<?= $active === 'products' ? 'is-active' : '' ?>">Products</a>
      <?php if ($user): ?>
        <a href="<?= e(url('orders.php')) ?>" class="<?= $active === 'orders' ? 'is-active' : '' ?>">My orders</a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
        <a href="<?= e(url('admin/index.php')) ?>" class="nav-admin">Admin panel</a>
      <?php endif; ?>
      <div class="nav-actions">
        <a class="cart-link" href="<?= e(url('cart.php')) ?>" aria-label="Cart, <?= $cartCount ?> items">
          Cart<span class="cart-count" id="cart-count"><?= $cartCount ?></span>
        </a>
        <?php if ($user): ?>
          <a class="btn btn-ghost" href="<?= e(url('profile.php')) ?>"><?= e(explode(' ', $user['full_name'])[0]) ?></a>
          <a class="btn btn-outline" href="<?= e(url('logout.php')) ?>">Log out</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= e(url('login.php')) ?>">Log in</a>
          <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Sign up</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<main id="main">
<?php foreach (flashes() as $flash): ?>
  <div class="container flash-wrap">
    <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
  </div>
<?php endforeach; ?>
