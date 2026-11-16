<?php
require __DIR__ . '/includes/bootstrap.php';

require_login();
$user = current_user();

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$ids = array_map('intval', array_keys($_SESSION['cart']));

if (!$ids) {
    flash('info', 'Your cart is empty. Add a product before checking out.');
    redirect('products.php');
}

$errors  = [];
$payment = post('payment_method', 'cod');
$allowedPayments = ['cod', 'gcash'];

// Pre-fill from the customer profile.
$form = [
    'full_name' => post('full_name') ?: $user['full_name'],
    'phone'     => post('phone') ?: (string) $user['phone'],
    'address'   => post('address') ?: (string) $user['address'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) < 3) {
        $errors[] = 'Please enter the recipient full name.';
    }
    if (!is_valid_phone($form['phone'])) {
        $errors[] = 'Please enter a valid contact number.';
    }
    if (mb_strlen($form['address']) < 10) {
        $errors[] = 'Please enter a complete delivery address.';
    }
    if (!in_array($payment, $allowedPayments, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "SELECT id, name, price, stock FROM products
                 WHERE id IN ($placeholders) AND is_active = 1 FOR UPDATE"
            );
            $stmt->execute($ids);
            $found = [];
            foreach ($stmt->fetchAll() as $row) {
                $found[(int) $row['id']] = $row;
            }

            $reference = order_reference();
            $items     = [];
            $total     = 0.0;

            foreach ($ids as $id) {
                $qty = (int) $_SESSION['cart'][$id];
                if (!isset($found[$id]) || $qty < 1) {
                    continue;
                }
                $row = $found[$id];
                if ((int) $row['stock'] < $qty) {
                    throw new RuntimeException($row['name'] . ' only has ' . (int) $row['stock'] . ' left in stock.');
                }
                $line = $qty * (float) $row['price'];
                $items[] = [
                    'product_id'   => (int) $row['id'],
                    'product_name' => $row['name'],
                    'quantity'     => $qty,
                    'unit_price'   => (float) $row['price'],
                    'line_total'   => $line,
                ];
                $total += $line;
            }

            if (!$items) {
                throw new RuntimeException('The items in your cart are no longer available.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO transactions
                 (reference, user_id, product_id, product_name, quantity, unit_price, total_amount, payment_method, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $decrement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($items as $item) {
                $insert->execute([
                    $reference,
                    (int) $user['id'],
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['line_total'],
                    $payment,
                    'pending',
                ]);
                $decrement->execute([$item['quantity'], $item['product_id']]);
            }

            $delivery = $pdo->prepare(
                'INSERT INTO order_delivery_tracking (reference, delivery_address)
                 VALUES (?, ?)'
            );
            $delivery->execute([$reference, $form['address']]);

            $pdo->commit();
            $_SESSION['cart'] = [];

            $mailUser = $user;
            $mailUser['address'] = $form['address'];
            $mailUser['phone']   = $form['phone'];
            $mailed = send_order_confirmation($mailUser, $reference, $items, $total, $payment);

            flash('success', 'Order ' . $reference . ' placed successfully'
                . ($mailed ? '. A confirmation email is on its way.' : '. The email confirmation could not be sent right now.'));
            redirect('orders.php?ref=' . urlencode($reference));
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
$active    = '';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div class="container">
  <div class="kicker">Checkout step 2 of 2</div>
  <h1>Confirm your order</h1>
  <p>Delivery details and payment method.</p>
</div></div>

<div class="container" style="padding-bottom:3rem">
  <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="cart-layout">
    <form class="panel" method="post" action="<?= e(url('checkout.php')) ?>">
      <?= csrf_field() ?>
      <h2>Delivery details</h2>
      <div class="field">
        <label for="full_name">Recipient name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="120">
      </div>
      <div class="field">
        <label for="phone">Contact number</label>
        <input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" required maxlength="30" placeholder="09XX XXX XXXX">
      </div>
      <div class="field">
        <label for="address">Delivery address</label>
        <textarea id="address" name="address" required maxlength="255" placeholder="House number, street, barangay, city"><?= e($form['address']) ?></textarea>
      </div>

      <h2 style="margin-top:1.5rem">Payment method</h2>
      <div class="payment-options">
        <label>
          <input type="radio" name="payment_method" value="cod" <?= $payment === 'cod' ? 'checked' : '' ?>>
          <span><strong>Cash on delivery</strong><br><small class="hint">Pay the rider when your order arrives.</small></span>
        </label>
        <label>
          <input type="radio" name="payment_method" value="gcash" <?= $payment === 'gcash' ? 'checked' : '' ?>>
          <span><strong>GCash</strong><br><small class="hint">Send payment to the seller's GCash number after checkout.</small></span>
        </label>
      </div>

      <button class="btn btn-primary btn-block" type="submit">Place order</button>
      <a class="btn btn-ghost btn-block" href="<?= e(url('cart.php')) ?>" style="margin-top:.5rem">Back to cart</a>
    </form>

    <aside class="panel">
      <h2>Order summary</h2>
      <?php
      $preview = db()->prepare(
          'SELECT p.id, p.name, p.price, p.stock FROM products p WHERE p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
      );
      $preview->execute($ids);
      $previewTotal = 0.0;
      foreach ($preview->fetchAll() as $row):
          $qty = min((int) $_SESSION['cart'][$row['id']], (int) $row['stock']);
          if ($qty < 1) { continue; }
          $line = $qty * (float) $row['price'];
          $previewTotal += $line;
      ?>
        <div class="summary-row">
          <span><?= e($row['name']) ?> × <?= $qty ?></span>
          <span><?= format_price($line) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="summary-row total"><span>Total</span><span><?= format_price($previewTotal) ?></span></div>
      <p class="hint">Prices include the platform commission. Delivery is arranged with the seller.</p>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
