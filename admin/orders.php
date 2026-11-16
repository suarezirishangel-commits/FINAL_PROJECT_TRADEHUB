<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Orders';
$adminActive = 'orders.php';

$allowedStatuses = ['pending', 'paid', 'out_for_delivery', 'completed', 'cancelled'];
$deliveryRiders = db()->query(
    'SELECT u.id, u.full_name, u.email
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.name = "Delivery Rider" AND u.status = "active"
     ORDER BY u.full_name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (post('action') === 'update') {
        $reference = trim(post('reference'));
        $status    = post('status');
        $tracking  = trim(post('tracking_number'));
        $riderInput = post('rider_id');
        $riderId = $riderInput === '' ? null : (int) $riderInput;
        $orderStmt = db()->prepare('SELECT payment_method, paid_at FROM transactions WHERE reference = ? LIMIT 1');
        $orderStmt->execute([$reference]);
        $orderInfo = $orderStmt->fetch();
        $validRider = $riderId === null;

        if ($riderId !== null) {
            $riderStmt = db()->prepare(
                'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? AND u.status = "active" AND r.name = "Delivery Rider"'
            );
            $riderStmt->execute([$riderId]);
            $validRider = (bool) $riderStmt->fetchColumn();
        }

        if ($reference === '' || !in_array($status, $allowedStatuses, true) || !$orderInfo) {
            flash('error', 'Could not update that order.');
        } elseif (mb_strlen($tracking) > 64) {
            flash('error', 'Tracking number must be 64 characters or fewer.');
        } elseif (!$validRider || ($riderId !== null && $orderInfo['payment_method'] !== 'cod')) {
            flash('error', 'Choose an active delivery rider for a COD order.');
        } elseif ($status === 'out_for_delivery' && $orderInfo['payment_method'] === 'cod' && $riderId === null) {
            flash('error', 'Assign a delivery rider before sending the order out.');
        } elseif ($status === 'completed' && $orderInfo['payment_method'] === 'cod' && $orderInfo['paid_at'] === null) {
            flash('error', 'Confirm that the rider collected the COD payment before completing the order.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
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

            $assigned = $pdo->prepare('SELECT rider_id FROM order_delivery_tracking WHERE reference = ? FOR UPDATE');
            $assigned->execute([$reference]);
            $currentRiderId = $assigned->fetchColumn();

            if ($currentRiderId === false) {
            if ($riderId !== null) {
                $pdo->prepare(
                    'INSERT INTO order_delivery_tracking (reference, rider_id) VALUES (?, ?)'
                )->execute([$reference, $riderId]);
            }
            } elseif ($riderId === null) {
            $pdo->prepare(
                'UPDATE order_delivery_tracking
                 SET rider_id = NULL, latitude = NULL, longitude = NULL,
                     location_updated_at = NULL, delivery_started_at = NULL
                 WHERE reference = ?'
            )->execute([$reference]);
            } elseif ((int) $currentRiderId !== $riderId) {
            $pdo->prepare(
                'UPDATE order_delivery_tracking
                 SET rider_id = ?, latitude = NULL, longitude = NULL,
                     location_updated_at = NULL, delivery_started_at = NULL
                 WHERE reference = ?'
            )->execute([$riderId, $reference]);
            }

            if ($riderId !== null && $status === 'out_for_delivery') {
            $pdo->prepare(
                'UPDATE order_delivery_tracking
                 SET delivery_started_at = COALESCE(delivery_started_at, NOW())
                 WHERE reference = ?'
            )->execute([$reference]);
            }
            $pdo->commit();
            flash('success', 'Order ' . $reference . ' updated.');
        }
    }

    redirect('admin/orders.php');
}

$rows = db()->query(
    'SELECT t.*, u.full_name, u.email, u.address AS profile_address,
            d.delivery_address, d.rider_id, d.latitude AS delivery_latitude,
            d.longitude AS delivery_longitude, d.location_updated_at, d.delivery_started_at
     FROM transactions t
     JOIN users u ON u.id = t.user_id
     LEFT JOIN order_delivery_tracking d ON d.reference = t.reference
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
            'payment_method'  => $row['payment_method'],
            'delivery_address' => $row['delivery_address'] !== null && $row['delivery_address'] !== ''
                ? $row['delivery_address'] : $row['profile_address'],
            'tracking_number' => $row['tracking_number'],
            'rider_id'        => $row['rider_id'] === null ? null : (int) $row['rider_id'],
            'location_updated_at' => $row['location_updated_at'],
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
  <div><h1>Orders</h1><p>Update order status, assign COD riders, and add courier tracking numbers.</p></div>
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
          <br><small class="hint">COD: <?= $order['payment_method'] === 'cod' ? 'Yes' : 'No' ?> · Deliver to: <?= e((string) ($order['delivery_address'] ?: 'No address provided')) ?></small>
        </span>
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

    <form class="order-manage" method="post" action="<?= e(url('admin/orders.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="reference" value="<?= e($order['reference']) ?>">
      <div class="field">
        <label for="status-<?= e($order['reference']) ?>">Status</label>
        <select id="status-<?= e($order['reference']) ?>" name="status">
          <?php foreach ($allowedStatuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="tracking-<?= e($order['reference']) ?>">Tracking number</label>
        <input type="text" id="tracking-<?= e($order['reference']) ?>" name="tracking_number"
               value="<?= e((string) $order['tracking_number']) ?>" maxlength="64"
               placeholder="e.g. J&T 0012345678">
      </div>
      <div class="field">
        <label for="rider-<?= e($order['reference']) ?>">Delivery rider (COD)</label>
        <select id="rider-<?= e($order['reference']) ?>" name="rider_id" <?= $order['payment_method'] !== 'cod' ? 'disabled' : '' ?>>
          <option value="">Unassigned</option>
          <?php foreach ($deliveryRiders as $rider): ?>
            <option value="<?= (int) $rider['id'] ?>" <?= (int) $order['rider_id'] === (int) $rider['id'] ? 'selected' : '' ?>>
              <?= e($rider['full_name']) ?> (<?= e($rider['email']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
    <?php if ($order['location_updated_at']): ?>
      <p class="hint" style="padding:0 1.15rem .8rem">Last rider GPS update: <?= e(date('M j, Y · g:i A', strtotime($order['location_updated_at']))) ?></p>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php require __DIR__ . '/_footer.php'; ?>
