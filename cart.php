<?php
require __DIR__ . '/includes/bootstrap.php';

$_SESSION['cart'] = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action    = post('action');
    $productId = (int) post('product_id');
    $quantity  = max(1, (int) post('quantity', '1'));

    $stmt = db()->prepare('SELECT id, name, stock FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        flash('error', 'That product is no longer available.');
        redirect('products.php');
    }

    if ($action === 'add') {
        if ((int) $product['stock'] <= 0) {
            flash('error', $product['name'] . ' is out of stock.');
            redirect('product.php?id=' . $productId);
        }
        $current = (int) ($_SESSION['cart'][$productId] ?? 0);
        $wanted  = min($current + $quantity, (int) $product['stock']);
        $_SESSION['cart'][$productId] = $wanted;
        flash('success', $product['name'] . ' was added to your cart.');
    } elseif ($action === 'update') {
        $_SESSION['cart'][$productId] = min($quantity, (int) $product['stock']);
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$productId]);
        flash('success', 'Item removed from your cart.');
    }

    redirect('cart.php');
}

// Load cart contents in one query.
$items = [];
$total = 0.0;
$ids   = array_map('intval', array_keys($_SESSION['cart']));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT p.id, p.name, p.price, p.stock, c.name AS category_name
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($placeholders) AND p.is_active = 1"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $row) {
        $qty = (int) $_SESSION['cart'][$row['id']];
        if ($qty < 1) {
            continue;
        }
        $qty = min($qty, max(0, (int) $row['stock']));
        if ($qty === 0) {
            continue;
        }
        $row['quantity'] = $qty;
        $row['line_total'] = $qty * (float) $row['price'];
        $total += $row['line_total'];
        $items[] = $row;
    }
}

$pageTitle = 'Your cart';
$active    = '';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head">
    <div class="kicker">Checkout step 1 of 2</div>
    <h1>Your cart</h1>
    <p><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> ready for checkout.</p>
  </div>

  <?php if (!$items): ?>
    <div class="empty" style="margin-bottom:3rem">
      <h3>Your cart is empty</h3>
      <p>Browse the catalog and add a few local favorites.</p>
      <a class="btn btn-primary" href="<?= e(url('products.php')) ?>">Browse products</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <div class="panel">
        <h2>Items</h2>
        <?php foreach ($items as $item): ?>
          <div class="cart-row">
            <div>
              <h3><a href="<?= e(url('product.php?id=' . (int) $item['id'])) ?>"><?= e($item['name']) ?></a></h3>
              <small><?= e($item['category_name']) ?> · <?= format_price($item['price']) ?> each</small>
            </div>
            <form class="qty-form" method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
              <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['stock'] ?>" data-max="<?= (int) $item['stock'] ?>" aria-label="Quantity for <?= e($item['name']) ?>">
              <button class="btn btn-outline btn-sm" type="submit">Update</button>
            </form>
            <form class="cart-remove" method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" aria-label="Remove <?= e($item['name']) ?>">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <aside class="panel">
        <h2>Order summary</h2>
        <?php foreach ($items as $item): ?>
          <div class="summary-row">
            <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
            <span><?= format_price($item['line_total']) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="summary-row total">
          <span>Subtotal</span>
          <span><?= format_price($total) ?></span>
        </div>
        <p class="hint" style="margin:.6rem 0 1rem">Delivery fees are arranged with the seller after checkout.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('checkout.php')) ?>">Proceed to checkout</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('products.php')) ?>" style="margin-top:.5rem">Continue shopping</a>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
