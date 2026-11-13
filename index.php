<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = setting('hero_title', 'Local marketplace');
$active    = 'home';

$stats = db()->query(
    'SELECT (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products,
            (SELECT COUNT(*) FROM categories) AS categories,
            (SELECT COUNT(DISTINCT reference) FROM transactions WHERE status <> "cancelled") AS orders'
)->fetch();

$featured = db()->query(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1
     ORDER BY p.stock > 0 DESC, p.created_at DESC
     LIMIT 8'
)->fetchAll();
$announcements = array_slice(storefront_announcements(), 0, 3);

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow">Local products · Fair prices</span>
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_text')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('products.php')) ?>">Browse products</a>
        <?php if (!current_user()): ?>
          <a class="btn btn-outline" href="<?= e(url('register.php')) ?>">Create a free account</a>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= e(url('orders.php')) ?>">View my orders</a>
        <?php endif; ?>
      </div>
      <div class="hero-stats">
        <div><strong><?= (int) $stats['products'] ?></strong><span>Products listed</span></div>
        <div><strong><?= (int) $stats['categories'] ?></strong><span>Local categories</span></div>
        <div><strong><?= (int) $stats['orders'] ?></strong><span>Orders placed</span></div>
      </div>
    </div>
  </div>
</section>

<?php if ($announcements): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <div class="kicker">Updates</div>
        <h2>Announcements</h2>
      </div>
    </div>
    <div class="highlights">
      <?php foreach ($announcements as $announcement): ?>
        <article class="highlight">
          <h3><?= e((string) $announcement['title']) ?></h3>
          <p><?= nl2br(e((string) $announcement['body'])) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <div class="kicker">What we offer</div>
        <h2>A marketplace built around discovery</h2>
        <p>Everything a buyer needs to find local goods quickly — and everything a seller needs to keep listings accurate.</p>
      </div>
    </div>
    <div class="highlights">
      <article class="highlight">
        <div class="icon" aria-hidden="true">🛍️</div>
        <h3>Product catalog</h3>
        <p>Browse verified local goods with clear pricing, category labels, and real-time stock availability.</p>
      </article>
      <article class="highlight">
        <div class="icon" aria-hidden="true">🔎</div>
        <h3>Advanced search</h3>
        <p>Full-text search across product names and descriptions, with sorting to surface the best match first.</p>
      </article>
      <article class="highlight">
        <div class="icon" aria-hidden="true">🎚️</div>
        <h3>Category &amp; price filters</h3>
        <p>Narrow results by category, price range, and availability so you only see what you can actually order.</p>
      </article>
      <article class="highlight">
        <div class="icon" aria-hidden="true">✉️</div>
        <h3>Order confirmations</h3>
        <p>Every checkout triggers an automated email receipt with your items, total, and delivery details.</p>
      </article>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <div>
        <div class="kicker">Fresh listings</div>
        <h2>Popular with local buyers</h2>
      </div>
      <a class="btn btn-outline" href="<?= e(url('products.php')) ?>">See all products</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $product): ?>
        <?php require __DIR__ . '/includes/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="highlight" style="background:#0f2f2b;color:#dfeeea;border-color:#0f2f2b;padding:2rem">
      <div class="kicker" style="color:#f59e0b">Our business</div>
      <h2 style="color:#fff">Supporting micro-entrepreneurs, one order at a time</h2>
      <p style="max-width:70ch;color:#bcd6d0"><?= e(setting('about_text')) ?></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
