<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Products';
$adminActive = 'products.php';
$errors      = [];

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// Load the product being edited (if any).
$editing = null;
if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $editing = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id          = (int) post('id');
        $name        = post('name');
        $categoryId  = (int) post('category_id');
        $description = post('description');
        $price       = post('price');
        $stock       = post('stock');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        $validCategory = false;
        foreach ($categories as $c) {
            if ((int) $c['id'] === $categoryId) {
                $validCategory = true;
            }
        }

        if (mb_strlen($name) < 3) {
            $errors[] = 'Product name must be at least 3 characters.';
        }
        if (!$validCategory) {
            $errors[] = 'Choose a valid category.';
        }
        if (!is_numeric($price) || (float) $price < 0) {
            $errors[] = 'Price must be a positive number.';
        }
        if (!ctype_digit($stock)) {
            $errors[] = 'Stock must be a whole number.';
        }

        if (!$errors) {
            $currentImage = null;
            if ($id > 0) {
                $imageStmt = db()->prepare('SELECT image FROM products WHERE id = ?');
                $imageStmt->execute([$id]);
                $currentImage = $imageStmt->fetchColumn() ?: null;
            }

            $upload = store_product_image($_FILES['image'] ?? []);
            if ($upload['error'] !== null) {
                $errors[] = $upload['error'];
            } elseif ($upload['path'] !== null) {
                delete_product_image($currentImage);
                $currentImage = $upload['path'];
            } elseif (!empty($_POST['remove_image'])) {
                delete_product_image($currentImage);
                $currentImage = null;
            }
        }

        if (!$errors) {
            if ($id > 0) {
                db()->prepare(
                    'UPDATE products SET category_id = ?, name = ?, description = ?, image = ?, price = ?, stock = ?, is_active = ? WHERE id = ?'
                )->execute([$categoryId, $name, $description, $currentImage, (float) $price, (int) $stock, $isActive, $id]);
                flash('success', 'Product updated.');
            } else {
                db()->prepare(
                    'INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$categoryId, $name, $description, $currentImage, (float) $price, (int) $stock, $isActive]);
                flash('success', 'Product added.');
            }
            redirect('admin/products.php');
        }
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([(int) post('id')]);
        flash('success', 'Product availability changed on the storefront.');
        redirect('admin/products.php');
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        try {
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash('success', 'Product deleted.');
        } catch (PDOException $e) {
            // Order history references this product, so keep the record and hide it instead.
            db()->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('info', 'This product has order history, so it was deactivated instead of deleted.');
        }
        redirect('admin/products.php');
    }
}

$showForm = isset($_GET['action']) && $_GET['action'] === 'new' || $editing !== null || $errors;

if ($errors && !$editing) {
    $editing = [
        'id'          => (int) post('id'),
        'name'        => post('name'),
        'category_id' => (int) post('category_id'),
        'description' => post('description'),
        'image'       => null,
        'price'       => post('price'),
        'stock'       => post('stock'),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];
}

$products = db()->query(
    'SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.is_active DESC, p.name'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Products</h1><p><?= count($products) ?> listings in the catalog.</p></div>
  <a class="btn btn-primary" href="<?= e(url('admin/products.php?action=new')) ?>">Add product</a>
</div>

<?php if ($errors): ?>
  <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($showForm): ?>
  <section class="card" style="margin-bottom:1.25rem">
    <div class="card-head"><h2><?= $editing && (int) $editing['id'] > 0 ? 'Edit product' : 'New product' ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php')) ?>">Cancel</a></div>
    <form method="post" action="<?= e(url('admin/products.php')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="form-grid">
        <div class="field span-2">
          <label for="name">Product name</label>
          <input type="text" id="name" name="name" value="<?= e((string) ($editing['name'] ?? '')) ?>" required maxlength="150">
        </div>
        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="price">Price (₱)</label>
          <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e((string) ($editing['price'] ?? '')) ?>" required>
        </div>
        <div class="field">
          <label for="stock">Stock quantity</label>
          <input type="number" id="stock" name="stock" min="0" value="<?= e((string) ($editing['stock'] ?? 0)) ?>" required>
        </div>
        <div class="field">
          <label for="is_active">Visibility</label>
          <label class="checkline" style="margin-top:.5rem">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
            Show in storefront
          </label>
        </div>
        <div class="field span-2">
          <label for="image">Product photo</label>
          <?php
            $previewImage = product_image_url($editing['image'] ?? null);
            $previewIcon  = product_icon('');
            foreach ($categories as $c) {
                if ((int) $c['id'] === (int) ($editing['category_id'] ?? 0)) {
                    $previewIcon = product_icon($c['name']);
                    break;
                }
            }
          ?>
          <div class="image-field">
            <div class="image-preview">
              <?php if ($previewImage): ?>
                <img src="<?= e($previewImage) ?>" alt="Current product photo">
              <?php else: ?>
                <span aria-hidden="true"><?= $previewIcon ?></span>
              <?php endif; ?>
            </div>
            <div class="image-field-body">
              <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
              <p class="hint">A clear photo that identifies the product. JPG, PNG, WebP, or GIF, up to 2MB.</p>
              <?php if ($previewImage): ?>
                <label class="checkline">
                  <input type="checkbox" name="remove_image" value="1">
                  Remove current photo
                </label>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="field span-2">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="2000"><?= e((string) ($editing['description'] ?? '')) ?></textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= $editing && (int) $editing['id'] > 0 ? 'Save changes' : 'Create product' ?></button>
    </form>
  </section>
<?php endif; ?>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div class="admin-product-cell">
              <span class="admin-thumb">
                <?php $thumb = product_image_url($p['image'] ?? null); ?>
                <?php if ($thumb): ?>
                  <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span aria-hidden="true"><?= product_icon($p['category_name']) ?></span>
                <?php endif; ?>
              </span>
              <strong><?= e($p['name']) ?></strong>
            </div>
          </td>
          <td><?= e($p['category_name']) ?></td>
          <td class="num"><?= format_price($p['price']) ?></td>
          <td class="num"><?= (int) $p['stock'] ?></td>
          <td>
            <?php if (!(int) $p['is_active']): ?>
              <span class="badge badge-out">Hidden</span>
            <?php elseif ((int) $p['stock'] === 0): ?>
              <span class="badge badge-out">Out of stock</span>
            <?php else: ?>
              <span class="badge badge-in">Live</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="row-actions">
              <a class="btn btn-outline btn-sm" href="<?= e(url('admin/products.php?id=' . (int) $p['id'])) ?>">Edit</a>
              <form class="inline-form" method="post" action="<?= e(url('admin/products.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $p['is_active'] ? 'Hide' : 'Show' ?></button>
              </form>
              <form class="inline-form" method="post" action="<?= e(url('admin/products.php')) ?>" onsubmit="return confirm('Delete this product?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?>
        <tr><td colspan="6" class="hint">No products yet. Add your first listing.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
