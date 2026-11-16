<?php
require __DIR__ . '/includes/bootstrap.php';
require_delivery_rider();

$rider = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $reference = post('reference');
    $action = post('action');
    $orderStmt = db()->prepare(
        'SELECT t.status, t.payment_method, t.paid_at
         FROM transactions t
         JOIN order_delivery_tracking d ON d.reference = t.reference
         WHERE t.reference = ? AND d.rider_id = ? AND t.payment_method = "cod"
         ORDER BY t.id
         LIMIT 1'
    );
    $orderStmt->execute([$reference, $rider['id']]);
    $order = $orderStmt->fetch();

    if (!$order || $reference === '') {
        flash('error', 'That COD order is not assigned to your account.');
    } elseif ($action === 'start' && in_array($order['status'], ['pending', 'paid'], true)) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE transactions SET status = "out_for_delivery" WHERE reference = ?'
        )->execute([$reference]);
        $pdo->prepare(
            'UPDATE order_delivery_tracking
             SET delivery_started_at = COALESCE(delivery_started_at, NOW())
             WHERE reference = ? AND rider_id = ?'
        )->execute([$reference, $rider['id']]);
        $pdo->commit();
        flash('success', 'Delivery started. Enable live GPS sharing while you are on the way.');
    } elseif ($action === 'collect' && $order['status'] === 'out_for_delivery') {
        db()->prepare(
            'UPDATE transactions SET paid_at = COALESCE(paid_at, NOW()) WHERE reference = ?'
        )->execute([$reference]);
        flash('success', 'COD payment marked as collected.');
    } elseif ($action === 'delivered' && $order['status'] === 'out_for_delivery' && $order['paid_at'] !== null) {
        db()->prepare(
            'UPDATE transactions
             SET status = "completed", completed_at = COALESCE(completed_at, NOW())
             WHERE reference = ?'
        )->execute([$reference]);
        flash('success', 'Order marked as delivered.');
    } else {
        flash('error', 'That delivery action is not available for the current order status.');
    }

    redirect('rider.php');
}

$rows = db()->prepare(
    'SELECT t.reference, t.product_name, t.quantity, t.total_amount, t.status,
            t.paid_at, t.created_at, u.full_name AS customer_name, u.phone,
            u.address AS profile_address, d.delivery_address, d.location_updated_at
     FROM transactions t
     JOIN users u ON u.id = t.user_id
     JOIN order_delivery_tracking d ON d.reference = t.reference
     WHERE d.rider_id = ? AND t.payment_method = "cod"
     ORDER BY t.created_at DESC, t.id DESC'
);
$rows->execute([$rider['id']]);

$orders = [];
foreach ($rows->fetchAll() as $row) {
    $reference = $row['reference'];
    if (!isset($orders[$reference])) {
        $orders[$reference] = [
            'reference' => $reference,
            'status' => $row['status'],
            'paid_at' => $row['paid_at'],
            'created_at' => $row['created_at'],
            'customer_name' => $row['customer_name'],
            'phone' => $row['phone'],
            'delivery_address' => $row['delivery_address'] !== '' ? $row['delivery_address'] : $row['profile_address'],
            'location_updated_at' => $row['location_updated_at'],
            'total' => 0.0,
            'items' => [],
        ];
    }

    $orders[$reference]['items'][] = [
        'name' => $row['product_name'],
        'quantity' => (int) $row['quantity'],
        'total' => (float) $row['total_amount'],
    ];
    $orders[$reference]['total'] += (float) $row['total_amount'];
    if (order_status_rank($row['status']) < order_status_rank($orders[$reference]['status'])) {
        $orders[$reference]['status'] = $row['status'];
    }
    if ($row['paid_at'] !== null) {
        $orders[$reference]['paid_at'] = $row['paid_at'];
    }
}

$pageTitle = 'Delivery dashboard';
$active = 'delivery';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head">
    <div class="kicker">Delivery rider</div>
    <h1>Assigned COD orders</h1>
    <p>Open this page on your phone, allow location access, and keep it open while sharing live delivery GPS.</p>
  </div>

  <div class="rider-orders" data-rider-tracking
       data-endpoint="<?= e(url('rider-location.php')) ?>"
       data-csrf="<?= e(csrf_token()) ?>">
    <?php if (!$orders): ?>
      <div class="empty">
        <h3>No assigned COD deliveries</h3>
        <p>Ask an administrator to assign a COD order to your rider account.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
      <article class="order-card rider-order">
        <div class="order-head">
          <div>
            <strong><?= e($order['reference']) ?></strong><br>
            <span class="hint"><?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:.8rem">
            <span class="badge badge-<?= e(str_replace('_', '-', $order['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></span>
            <strong><?= format_price($order['total']) ?></strong>
          </div>
        </div>

        <div class="rider-order-details">
          <p><strong>Customer:</strong> <?= e($order['customer_name']) ?><?= $order['phone'] ? ' · ' . e($order['phone']) : '' ?></p>
          <p><strong>Deliver to:</strong> <?= e((string) ($order['delivery_address'] ?: 'No delivery address provided')) ?></p>
          <ul class="order-items">
            <?php foreach ($order['items'] as $item): ?>
              <li><span><?= e($item['name']) ?> × <?= $item['quantity'] ?></span><span><?= format_price($item['total']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="rider-actions">
          <?php if (in_array($order['status'], ['pending', 'paid'], true)): ?>
            <form method="post" action="<?= e(url('rider.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="start">
              <input type="hidden" name="reference" value="<?= e($order['reference']) ?>">
              <button class="btn btn-primary" type="submit">Start delivery</button>
            </form>
          <?php elseif ($order['status'] === 'out_for_delivery'): ?>
            <div class="rider-gps-controls">
              <button class="btn btn-outline" type="button" data-gps-toggle data-reference="<?= e($order['reference']) ?>">Start live GPS</button>
              <p class="hint" data-gps-status aria-live="polite">
                <?= $order['location_updated_at'] ? 'Last update: ' . e(date('M j, Y · g:i A', strtotime($order['location_updated_at']))) : 'GPS has not been shared yet.' ?>
              </p>
            </div>
            <?php if ($order['paid_at'] === null): ?>
              <form method="post" action="<?= e(url('rider.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="collect">
                <input type="hidden" name="reference" value="<?= e($order['reference']) ?>">
                <button class="btn btn-outline" type="submit">Mark COD payment collected</button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= e(url('rider.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delivered">
                <input type="hidden" name="reference" value="<?= e($order['reference']) ?>">
                <button class="btn btn-primary" type="submit">Mark delivered</button>
              </form>
            <?php endif; ?>
          <?php elseif ($order['status'] === 'completed'): ?>
            <span class="hint">Delivery completed<?= $order['paid_at'] ? ' · COD payment collected' : '' ?>.</span>
          <?php elseif ($order['status'] === 'cancelled'): ?>
            <span class="hint">This order was cancelled.</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<script src="<?= e(url('assets/js/rider-tracking.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
