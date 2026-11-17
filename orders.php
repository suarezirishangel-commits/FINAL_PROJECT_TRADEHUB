<?php
require __DIR__ . '/includes/bootstrap.php';

require_login();
$user = current_user();

$stmt = db()->prepare(
    'SELECT t.*, d.rider_id, d.delivery_address, d.latitude AS delivery_latitude,
            d.longitude AS delivery_longitude, d.location_updated_at, d.delivery_started_at
     FROM transactions t
     LEFT JOIN order_delivery_tracking d ON d.reference = t.reference
     WHERE t.user_id = ?
     ORDER BY t.created_at DESC, t.id DESC'
);
$stmt->execute([$user['id']]);

$orders = [];
foreach ($stmt->fetchAll() as $row) {
    $ref = $row['reference'];
    if (!isset($orders[$ref])) {
        $orders[$ref] = [
            'reference'      => $ref,
            'created_at'     => $row['created_at'],
            'status'          => $row['status'],
            'payment_method'  => $row['payment_method'],
            'tracking_number' => $row['tracking_number'],
            'paid_at'         => $row['paid_at'],
            'completed_at'    => $row['completed_at'],
            'cancelled_at'    => $row['cancelled_at'],
            'rider_id'        => $row['rider_id'],
            'delivery_latitude' => $row['delivery_latitude'],
            'delivery_longitude' => $row['delivery_longitude'],
            'location_updated_at' => $row['location_updated_at'],
            'delivery_started_at' => $row['delivery_started_at'],
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

$hasLiveOrders = false;
foreach ($orders as $order) {
    if ($order['rider_id'] !== null) {
        $hasLiveOrders = true;
        break;
    }
}

$highlight = (string) ($_GET['ref'] ?? '');
$pageTitle = 'My orders';
$active    = 'orders';
$includeLeaflet = $hasLiveOrders;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head">
    <div class="kicker">Order history</div>
    <h1>My orders</h1>
    <p>Every checkout you have made, newest first, with its delivery progress.</p>
  </div>

  <div style="padding-bottom:3rem">
    <?php if (!$orders): ?>
      <div class="empty">
        <h3>No orders yet</h3>
        <p>Your completed checkouts will show up here.</p>
        <a class="btn btn-primary" href="<?= e(url('products.php')) ?>">Start shopping</a>
      </div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
      <article class="order-card" <?= $order['reference'] === $highlight ? 'style="border-color:var(--brand);box-shadow:var(--shadow)"' : '' ?>>
        <div class="order-head">
          <div>
            <strong><?= e($order['reference']) ?></strong><br>
            <span class="hint"><?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?> · <?= e(strtoupper($order['payment_method'])) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:.8rem">
            <span class="badge badge-<?= e(str_replace('_', '-', $order['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></span>
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

        <div class="order-track">
          <ol class="track-timeline">
            <?php foreach (order_tracking_steps($order) as $step): ?>
              <li class="<?= $step['done'] ? 'is-done' : '' ?><?= $step['cancelled'] ? ' is-cancelled' : '' ?>">
                <span class="track-dot" aria-hidden="true"></span>
                <strong><?= e($step['label']) ?></strong>
                <small><?php
                  if ($step['at']) {
                      echo e(date('M j, Y · g:i A', strtotime($step['at'])));
                  } else {
                      echo $step['done'] ? 'Done' : 'Waiting';
                  }
                ?></small>
              </li>
            <?php endforeach; ?>
          </ol>
          <?php if ($order['rider_id'] !== null): ?>
            <section class="live-tracking" aria-label="Live delivery tracking">
              <h3>Track delivery</h3>
              <p class="hint">Live location is shared while the rider is on delivery.</p>
              <div class="order-map" data-order-map
                   data-reference="<?= e($order['reference']) ?>"
                   data-endpoint="<?= e(url('order-location.php')) ?>">
                <p class="map-status" data-map-status aria-live="polite">
                  <?= $order['rider_id'] === null ? 'Waiting for a rider to be assigned.' : 'Waiting for the rider to start delivery and share GPS.' ?>
                </p>
                <div class="delivery-map" data-map-canvas hidden></div>
              </div>
            </section>
          <?php endif; ?>
          <div class="track-meta">
            <?php if (!empty($order['tracking_number'])): ?>
              <span>Tracking number: <strong><?= e($order['tracking_number']) ?></strong></span>
            <?php elseif ($order['status'] === 'cancelled'): ?>
              <span class="hint">This order was cancelled.</span>
            <?php else: ?>
              <span class="hint">No tracking number yet. It will appear here once the seller hands your order to the courier.</span>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
  <?php if ($hasLiveOrders): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <script src="<?= e(url('assets/js/order-map.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/js/order-map.js') ?>" defer></script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
