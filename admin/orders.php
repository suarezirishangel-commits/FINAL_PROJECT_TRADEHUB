<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Orders';
$adminActive = 'orders.php';

$allowedStatuses = ['pending', 'paid', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (post('action') === 'update') {
        $reference = trim(post('reference'));
        $status    = post('status');
        $tracking  = trim(post('tracking_number'));

        if ($reference === '' || !in_array($status, $allowedStatuses, true)) {
            flash('error', 'Could not update that order.');
        } elseif (mb_strlen($tracking) > 64) {
            flash('error', 'Tracking number must be 64 characters or fewer.');
        } else {
            // Keep the first time an order reached each status.
            db()->prepare(
                "UPDATE transactions
                 SET status = ?,
                     tracking_number = ?,
                     paid_at = IF(? IN ('paid', 'completed'), COALESCE(paid_at, NOW()), paid_at),
                     completed_at = IF(? = 'completed', COALESCE(completed_at, NOW()), completed_at),
                     cancelled_at = IF(? = 'cancelled', COALESCE(cancelled_at, NOW()), cancelled_at)
                 WHERE reference = ?"
            )->execute([
                $status,
                $tracking !== '' ? $tracking : null,
                $status,
                $status,
                $status,
                $reference,
            ]);
            flash('success', 'Order ' . $reference . ' updated.');
        }
    }

    redirect('admin/orders.php');
}

$rows = db()->query(
    'SELECT t.*, u.full_name, u.email
     FROM transactions t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC'
)->fetchAll();

$orders = [];
foreach ($rows as $row) {
    $ref = $row['reference'];
    if (!isset($orders[$ref])) {
        $orders[$ref] = [
            'reference'       => $ref,
            'customer'        => $row['full_name'],
            'email'           => $row['email'],
            'created_at'      => $row['created_at'],
            'status'          => $row['status'],
            'tracking_number' => $row['tracking_number'],
            'total'           => 0.0,
            'items'           => [],
        ];
    }
    $orders[$ref]['items'][] = $row;
    $orders[$ref]['total']  += (float) $row['total_amount'];
    // An order is only as complete as its least-advanced line.
    if (order_status_rank($row['status']) < order_status_rank($orders[$ref]['status'])) {
        $orders[$ref]['status'] = $row['status'];
    }
}

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Orders</h1><p>Update order status and add a tracking number for customers.</p></div>
  <span class="hint"><?= count($orders) ?> orders</span>
</div>

<?php if (!$orders): ?>
  <div class="empty">
    <h3>No orders yet</h3>
    <p>Orders placed at checkout will show up here.</p>
  </div>
<?php endif; ?>

<?php foreach ($orders as $order): ?>
  <article class="order-card">
    <div class="order-head">
      <div>
        <strong><?= e($order['reference']) ?></strong><br>
        <span class="hint">
          <?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?>
          · <?= e($order['customer']) ?> (<?= e($order['email']) ?>)
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:.8rem">
        <span class="badge badge-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
        <strong><?= format_price($order['total']) ?></strong>
      </div>
    </div>

    <ul class="order-items">
      <?php foreach ($order['items'] as $item): ?>
        <li>
          <span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
          <span><?= format_price($item['total_amount']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <form class="order-manage" method="post" action="<?= e(url('admin/orders.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="reference" value="<?= e($order['reference']) ?>">
      <div class="field">
        <label for="status-<?= e($order['reference']) ?>">Status</label>
        <select id="status-<?= e($order['reference']) ?>" name="status">
          <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="tracking-<?= e($order['reference']) ?>">Tracking number</label>
        <input type="text" id="tracking-<?= e($order['reference']) ?>" name="tracking_number"
               value="<?= e((string) $order['tracking_number']) ?>" maxlength="64"
               placeholder="e.g. J&T 0012345678">
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </article>
<?php endforeach; ?>
<?php require __DIR__ . '/_footer.php'; ?>
