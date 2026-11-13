<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_active = 1'
);
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty" style="margin:3rem 0"><h1>Product not found</h1>'
        . '<p>This listing may have been removed.</p><a class="btn btn-primary" href="' . e(url('products.php')) . '">Back to products</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'];
$active    = 'products';
$stock     = (int) $product['stock'];

$relatedStmt = db()->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1
     ORDER BY p.stock > 0 DESC, p.name LIMIT 3'
);
$relatedStmt->execute([$product['category_id'], $product['id']]);
$related = $relatedStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <p class="hint" style="padding-top:1.5rem;font-size:.85rem">
    <a href="<?= e(url('index.php')) ?>">Home</a> /
    <a href="<?= e(url('products.php')) ?>">Products</a> /
    <a href="<?= e(url('products.php?category=' . (int) $product['category_id'])) ?>"><?= e($product['category_name']) ?></a>
  </p>

  <div class="detail">
    <div class="detail-media tone-<?= product_tone((int) $product['id']) ?>">
      <?php $imageUrl = product_image_url($product['image'] ?? null); ?>
      <?php if ($imageUrl): ?>
        <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
      <?php else: ?>
        <span class="thumb-icon" aria-hidden="true"><?= product_icon($product['category_name']) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <span class="product-cat"><?= e($product['category_name']) ?></span>
      <h1><?= e($product['name']) ?></h1>
      <p class="price"><?= format_price($product['price']) ?></p>
      <p><?= nl2br(e($product['description'])) ?></p>

      <ul class="specs">
        <li><span>Availability</span>
          <span>
            <?php if ($stock <= 0): ?>
              <span class="badge badge-out">Out of stock</span>
            <?php elseif ($stock <= 10): ?>
              <span class="badge badge-low"><?= $stock ?> left in stock</span>
            <?php else: ?>
              <span class="badge badge-in"><?= $stock ?> in stock</span>
            <?php endif; ?>
          </span>
        </li>
        <li><span>Category</span><span><?= e($product['category_name']) ?></span></li>
        <li><span>Listed</span><span><?= e(date('M j, Y', strtotime($product['created_at']))) ?></span></li>
      </ul>

      <?php if ($stock > 0): ?>
        <form class="buy-form" method="post" action="<?= e(url('cart.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
          <div class="field">
            <label for="qty">Quantity</label>
            <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $stock ?>" data-max="<?= $stock ?>" required>
          </div>
          <button class="btn btn-primary" type="submit">Add to cart</button>
          <a class="btn btn-outline" href="<?= e(url('cart.php')) ?>">Go to cart</a>
        </form>
      <?php else: ?>
        <p class="badge badge-out" style="padding:.5rem .8rem">This product is currently unavailable. Check back soon.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="section" style="padding-top:0">
      <div class="section-head"><div><div class="kicker">More in this category</div><h2>You may also like</h2></div></div>
      <div class="product-grid">
        <?php foreach ($related as $product): ?>
          <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
