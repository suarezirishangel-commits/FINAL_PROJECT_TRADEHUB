<?php
/** @var array $product Requires id, name, price, stock, category_name, category_slug */
$stock      = (int) $product['stock'];
$imageUrl   = product_image_url($product['image'] ?? null);
?>
<article class="product-card">
  <div class="product-thumb tone-<?= product_tone((int) $product['id']) ?>">
    <?php if ($imageUrl): ?>
      <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
    <?php else: ?>
      <span class="thumb-icon" aria-hidden="true"><?= product_icon($product['category_name']) ?></span>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-cat"><?= e($product['category_name']) ?></span>
    <h3><a href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a></h3>
    <div class="product-meta">
      <span class="price"><?= format_price($product['price']) ?></span>
      <?php if ($stock <= 0): ?>
        <span class="badge badge-out">Out of stock</span>
      <?php elseif ($stock <= 10): ?>
        <span class="badge badge-low">Only <?= $stock ?> left</span>
      <?php else: ?>
        <span class="badge badge-in">In stock</span>
      <?php endif; ?>
    </div>
    <a class="btn btn-outline btn-sm btn-block" href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>">View product</a>
  </div>
</article>
