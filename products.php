<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Products';
$active    = 'products';

$q          = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$minPrice   = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$maxPrice   = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$available  = !empty($_GET['available']);
$sort       = (string) ($_GET['sort'] ?? 'newest');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 9;

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$where  = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($minPrice !== null) {
    $where[]  = 'p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[]  = 'p.price <= ?';
    $params[] = $maxPrice;
}
if ($available) {
    $where[] = 'p.stock > 0';
}

$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC, p.name ASC',
    'price_desc' => 'p.price DESC, p.name ASC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC, p.id DESC',
};

$sqlWhere = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $sqlWhere");
$countStmt->execute($params);
$total     = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE $sqlWhere ORDER BY $orderBy LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

/** Build the current URL with some query values replaced. */
function catalog_url(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, static fn ($v) => $v !== '' && $v !== null);
    return url('products.php' . ($query ? '?' . http_build_query($query) : ''));
}

$activeCategory = null;
foreach ($categories as $c) {
    if ((int) $c['id'] === $categoryId) {
        $activeCategory = $c['name'];
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head">
    <div class="kicker">Product catalog</div>
    <h1>Browse local products</h1>
    <p>Search by name, filter by category and price, and only order what is in stock.</p>
  </div>

  <div class="catalog">
    <aside class="filters">
      <h2>Filter products</h2>
      <form method="get" action="<?= e(url('products.php')) ?>">
        <div class="field">
          <label for="q">Search</label>
          <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. mangoes, coffee">
        </div>
        <div class="field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Price range (₱)</label>
          <div class="field-row">
            <input type="number" name="min_price" min="0" step="0.01" placeholder="Min" value="<?= $minPrice !== null ? e((string) $minPrice) : '' ?>">
            <input type="number" name="max_price" min="0" step="0.01" placeholder="Max" value="<?= $maxPrice !== null ? e((string) $maxPrice) : '' ?>">
          </div>
        </div>
        <label class="checkline">
          <input type="checkbox" name="available" value="1" <?= $available ? 'checked' : '' ?>>
          In stock only
        </label>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="page" value="1">
        <div class="filter-actions">
          <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
          <a class="btn btn-ghost btn-block" href="<?= e(url('products.php')) ?>">Reset</a>
        </div>
      </form>
    </aside>

    <div>
      <div class="catalog-toolbar">
        <form class="search-bar" method="get" action="<?= e(url('products.php')) ?>">
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…" aria-label="Search products">
          <?php if ($categoryId): ?><input type="hidden" name="category" value="<?= $categoryId ?>"><?php endif; ?>
          <?php if ($available): ?><input type="hidden" name="available" value="1"><?php endif; ?>
          <input type="hidden" name="sort" value="<?= e($sort) ?>">
          <button class="btn btn-primary" type="submit">Search</button>
        </form>
        <form method="get" action="<?= e(url('products.php')) ?>">
          <?php foreach (['q' => $q, 'category' => $categoryId ?: '', 'min_price' => $minPrice, 'max_price' => $maxPrice, 'available' => $available ? 1 : ''] as $key => $value): ?>
            <?php if ($value !== '' && $value !== null): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
          <?php endforeach; ?>
          <label class="hint" for="sort" style="margin:0 .3rem 0 0">Sort</label>
          <select id="sort" name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
          </select>
        </form>
      </div>

      <div class="active-filters">
        <?php if ($q !== ''): ?><a class="chip" href="<?= e(catalog_url(['q' => null, 'page' => 1])) ?>">“<?= e($q) ?>” ✕</a><?php endif; ?>
        <?php if ($activeCategory): ?><a class="chip" href="<?= e(catalog_url(['category' => null, 'page' => 1])) ?>"><?= e($activeCategory) ?> ✕</a><?php endif; ?>
        <?php if ($minPrice !== null || $maxPrice !== null): ?>
          <a class="chip" href="<?= e(catalog_url(['min_price' => null, 'max_price' => null, 'page' => 1])) ?>">
            ₱<?= $minPrice !== null ? number_format($minPrice, 0) : '0' ?> – ₱<?= $maxPrice !== null ? number_format($maxPrice, 0) : 'any' ?> ✕
          </a>
        <?php endif; ?>
        <?php if ($available): ?><a class="chip" href="<?= e(catalog_url(['available' => null, 'page' => 1])) ?>">In stock ✕</a><?php endif; ?>
      </div>

      <p class="count" style="color:var(--muted);font-size:.9rem;margin:0 0 1rem">
        <?= $total ?> product<?= $total === 1 ? '' : 's' ?> found
      </p>

      <?php if ($products): ?>
        <div class="product-grid">
          <?php foreach ($products as $product): ?>
            <?php require __DIR__ . '/includes/product-card.php'; ?>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="pagination" aria-label="Product pages">
            <?php if ($page > 1): ?>
              <a href="<?= e(catalog_url(['page' => $page - 1])) ?>">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i === $page): ?>
                <span class="is-current" aria-current="page"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= e(catalog_url(['page' => $i])) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
              <a href="<?= e(catalog_url(['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty">
          <h3>No products match your filters</h3>
          <p>Try a broader search term, a different category, or a wider price range.</p>
          <a class="btn btn-primary" href="<?= e(url('products.php')) ?>">Clear all filters</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
