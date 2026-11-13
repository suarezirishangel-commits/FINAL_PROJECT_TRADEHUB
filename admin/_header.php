<?php
if (!defined('APP_BASE')) {
    require __DIR__ . '/../includes/bootstrap.php';
}

require_admin();

$adminTitle  = $adminTitle ?? 'Dashboard';
$adminActive = $adminActive ?? '';
$adminUser   = current_user();
$nav = [
    'index.php'         => 'Dashboard',
    'products.php'      => 'Products',
    'categories.php'    => 'Categories',
    'orders.php'        => 'Orders',
    'users.php'         => 'Users',
    'content.php'       => 'Content & announcements',
    'reports.php'       => 'Sales reports',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle) ?> · <?= e(setting('site_name', 'TradeHub')) ?> Admin</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-side">
    <a class="brand brand-light" href="<?= e(url('admin/index.php')) ?>">
      <span class="brand-mark">TH</span>
      <span class="brand-name"><?= e(setting('site_name', 'TradeHub')) ?><small>Admin panel</small></span>
    </a>
    <nav aria-label="Admin navigation">
      <?php foreach ($nav as $file => $label): ?>
        <a href="<?= e(url('admin/' . $file)) ?>" class="<?= $adminActive === $file ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <div class="side-sep"></div>
      <a href="<?= e(url('index.php')) ?>">View storefront</a>
      <a href="<?= e(url('logout.php')) ?>">Log out</a>
    </nav>
  </aside>
  <div class="admin-main">
    <?php foreach (flashes() as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
