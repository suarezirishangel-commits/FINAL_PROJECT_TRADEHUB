<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Dashboard';
$adminActive = 'index.php';

$totals = db()->query(
    "SELECT
       COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_amount END), 0) AS revenue,
       COUNT(DISTINCT reference) AS orders,
       SUM(status = 'pending') AS pending_lines
     FROM transactions"
)->fetch();

$counts = db()->query(
    "SELECT
       (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products,
       (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'Customer') AS customers
    "
)->fetch();

$commission = (float) $totals['revenue'] * ((float) setting('commission_rate', '0') / 100);

$recent = db()->query(
    "SELECT t.reference, t.product_name, t.quantity, t.total_amount, t.status, t.created_at, u.full_name
     FROM transactions t JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC LIMIT 8"
)->fetchAll();

$lowStock = db()->query(
    'SELECT p.id, p.name, p.stock, c.name AS category_name
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.stock <= 10
     ORDER BY p.stock ASC, p.name LIMIT 6'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div>
    <h1>Dashboard</h1>
    <p>Marketplace activity at a glance.</p>
  </div>
  <div class="admin-actions">
    <a class="btn btn-outline" href="<?= e(url('admin/reports.php')) ?>">Sales report</a>
    <a class="btn btn-primary" href="<?= e(url('admin/products.php?action=new')) ?>">Add product</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><span>Total sales</span><strong><?= format_price($totals['revenue']) ?></strong><small><?= (int) $totals['orders'] ?> orders</small></div>
  <div class="stat"><span>Commission (<?= e(setting('commission_rate', '0')) ?>%)</span><strong><?= format_price($commission) ?></strong><small>Platform revenue</small></div>
  <div class="stat"><span>Active products</span><strong><?= (int) $counts['products'] ?></strong><small><?= (int) $totals['pending_lines'] ?> pending line items</small></div>
  <div class="stat"><span>Customers</span><strong><?= (int) $counts['customers'] ?></strong><small>Registered buyers</small></div>
</div>

<div class="admin-grid">
  <section class="card">
    <div class="card-head">
      <h2>Recent transactions</h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports.php')) ?>">View all</a>
    </div>
    <?php if ($recent): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Reference</th><th>Customer</th><th>Item</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td><?= e($row['reference']) ?><br><small class="hint"><?= e(date('M j, g:i A', strtotime($row['created_at']))) ?></small></td>
              <td><?= e($row['full_name']) ?></td>
              <td><?= e($row['product_name']) ?> × <?= (int) $row['quantity'] ?></td>
              <td class="num"><?= format_price($row['total_amount']) ?></td>
              <td><span class="badge badge-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="hint">No transactions yet.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Low stock alerts</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php')) ?>">Manage</a></div>
    <?php if ($lowStock): ?>
      <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($lowStock as $item): ?>
          <li style="display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
            <span><?= e($item['name']) ?><br><small class="hint"><?= e($item['category_name']) ?></small></span>
            <span class="badge <?= (int) $item['stock'] === 0 ? 'badge-out' : 'badge-low' ?>"><?= (int) $item['stock'] === 0 ? 'Out of stock' : (int) $item['stock'] . ' left' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="hint">All active products have healthy stock levels.</p>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
