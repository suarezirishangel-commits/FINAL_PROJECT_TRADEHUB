<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Sales reports';
$adminActive = 'reports.php';

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');

$params = [$from . ' 00:00:00', $to . ' 23:59:59'];

$summaryStmt = db()->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS gross,
            COUNT(DISTINCT reference) AS orders,
            COALESCE(SUM(quantity), 0) AS units
     FROM transactions
     WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ?"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$rate       = (float) setting('commission_rate', '0');
$commission = (float) $summary['gross'] * $rate / 100;
$avgOrder   = (int) $summary['orders'] > 0 ? (float) $summary['gross'] / (int) $summary['orders'] : 0.0;

$byCategory = db()->prepare(
    "SELECT c.name, SUM(t.total_amount) AS revenue, SUM(t.quantity) AS units
     FROM transactions t
     JOIN products p ON p.id = t.product_id
     JOIN categories c ON c.id = p.category_id
     WHERE t.status <> 'cancelled' AND t.created_at BETWEEN ? AND ?
     GROUP BY c.id, c.name ORDER BY revenue DESC"
);
$byCategory->execute($params);
$categoryRows = $byCategory->fetchAll();
$maxCategory  = 0.0;
foreach ($categoryRows as $row) {
    $maxCategory = max($maxCategory, (float) $row['revenue']);
}

$topProducts = db()->prepare(
    "SELECT t.product_name, SUM(t.quantity) AS units, SUM(t.total_amount) AS revenue
     FROM transactions t
     WHERE t.status <> 'cancelled' AND t.created_at BETWEEN ? AND ?
     GROUP BY t.product_name ORDER BY revenue DESC LIMIT 5"
);
$topProducts->execute($params);
$topRows = $topProducts->fetchAll();

$logStmt = db()->prepare(
    "SELECT t.reference, t.product_name, t.quantity, t.total_amount, t.payment_method, t.status, t.created_at, u.full_name
     FROM transactions t JOIN users u ON u.id = t.user_id
     WHERE t.created_at BETWEEN ? AND ?
     ORDER BY t.created_at DESC, t.id DESC LIMIT 50"
);
$logStmt->execute($params);
$logRows = $logStmt->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Sales reports</h1><p>Sales, commission, and best sellers for a date range.</p></div>
  <form method="get" action="<?= e(url('admin/reports.php')) ?>" class="admin-actions" style="align-items:flex-end">
    <div class="field" style="margin:0">
      <label for="from">From</label>
      <input type="date" id="from" name="from" value="<?= e($from) ?>" required>
    </div>
    <div class="field" style="margin:0">
      <label for="to">To</label>
      <input type="date" id="to" name="to" value="<?= e($to) ?>" required>
    </div>
    <button class="btn btn-primary" type="submit">Generate</button>
  </form>
</div>

<div class="stat-grid">
  <div class="stat"><span>Gross sales</span><strong><?= format_price($summary['gross']) ?></strong><small><?= (int) $summary['orders'] ?> orders, <?= (int) $summary['units'] ?> units</small></div>
  <div class="stat"><span>Platform commission</span><strong><?= format_price($commission) ?></strong><small><?= e(number_format($rate, 2)) ?>% of gross</small></div>
  <div class="stat"><span>Seller payout</span><strong><?= format_price((float) $summary['gross'] - $commission) ?></strong><small>After commission</small></div>
  <div class="stat"><span>Average order</span><strong><?= format_price($avgOrder) ?></strong><small><?= e($from) ?> to <?= e($to) ?></small></div>
</div>

<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Sales by category</h2></div>
    <?php if ($categoryRows): ?>
      <?php foreach ($categoryRows as $row): ?>
        <div style="margin-bottom:.9rem">
          <div style="display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:.3rem">
            <span><?= e($row['name']) ?> <small class="hint">(<?= (int) $row['units'] ?> units)</small></span>
            <strong><?= format_price($row['revenue']) ?></strong>
          </div>
          <div style="height:8px;background:#eef2f1;border-radius:999px;overflow:hidden">
            <div style="height:100%;width:<?= $maxCategory > 0 ? round((float) $row['revenue'] / $maxCategory * 100) : 0 ?>%;background:var(--brand)"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="hint">No sales recorded in this period.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Top products</h2></div>
    <?php if ($topRows): ?>
      <ul style="list-style:none;padding:0;margin:0">
        <?php foreach ($topRows as $row): ?>
          <li style="display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
            <span><?= e($row['product_name']) ?><br><small class="hint"><?= (int) $row['units'] ?> sold</small></span>
            <strong><?= format_price($row['revenue']) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="hint">Nothing sold in this range yet.</p>
    <?php endif; ?>
  </section>
</div>

<section class="card" style="margin-top:1.25rem">
  <div class="card-head"><h2>Transaction log</h2><small class="hint">Latest 50 records in range</small></div>
  <div class="table-wrap" style="box-shadow:none">
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Item</th><th class="num">Qty</th><th class="num">Amount</th><th>Payment</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($logRows as $row): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime($row['created_at']))) ?><br><small class="hint"><?= e(date('g:i A', strtotime($row['created_at']))) ?></small></td>
            <td><?= e($row['reference']) ?></td>
            <td><?= e($row['full_name']) ?></td>
            <td><?= e($row['product_name']) ?></td>
            <td class="num"><?= (int) $row['quantity'] ?></td>
            <td class="num"><?= format_price($row['total_amount']) ?></td>
            <td><?= e(strtoupper($row['payment_method'])) ?></td>
            <td><span class="badge badge-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logRows): ?>
          <tr><td colspan="8" class="hint">No transactions in this date range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
