<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Categories';
$adminActive = 'categories.php';
$errors      = [];

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

$editing = null;
if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $editing = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id          = (int) post('id');
        $name        = post('name');
        $description = post('description');
        $slug        = slugify(post('slug') !== '' ? post('slug') : $name);

        if (mb_strlen($name) < 2) {
            $errors[] = 'Category name is too short.';
        }
        if ($slug === '') {
            $errors[] = 'Enter a category name with letters or numbers.';
        }

        if (!$errors) {
            $dupe = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
            $dupe->execute([$slug, $id]);
            if ($dupe->fetch()) {
                $errors[] = 'Another category already uses that URL slug.';
            }
        }

        if (!$errors) {
            if ($id > 0) {
                db()->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?')
                    ->execute([$name, $slug, $description, $id]);
                flash('success', 'Category updated.');
            } else {
                db()->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')
                    ->execute([$name, $slug, $description]);
                flash('success', 'Category added.');
            }
            redirect('admin/categories.php');
        }
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        $used = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $used->execute([$id]);
        if ((int) $used->fetchColumn() > 0) {
            flash('error', 'Move or delete the products in this category first.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

$categories = db()->query(
    'SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Categories</h1><p>Group listings so buyers can filter the catalog.</p></div>
  <a class="btn btn-primary" href="<?= e(url('admin/categories.php?action=new')) ?>">Add category</a>
</div>

<?php if ($errors): ?>
  <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($editing !== null || isset($_GET['action'])): ?>
  <section class="card" style="margin-bottom:1.25rem">
    <div class="card-head"><h2><?= $editing ? 'Edit category' : 'New category' ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/categories.php')) ?>">Cancel</a></div>
    <form method="post" action="<?= e(url('admin/categories.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="form-grid">
        <div class="field">
          <label for="name">Category name</label>
          <input type="text" id="name" name="name" value="<?= e((string) ($editing['name'] ?? '')) ?>" required maxlength="100">
        </div>
        <div class="field">
          <label for="slug">URL slug <span class="hint">(optional)</span></label>
          <input type="text" id="slug" name="slug" value="<?= e((string) ($editing['slug'] ?? '')) ?>" maxlength="120">
        </div>
        <div class="field span-2">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="500"><?= e((string) ($editing['description'] ?? '')) ?></textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create category' ?></button>
    </form>
  </section>
<?php endif; ?>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Category</th><th>Slug</th><th class="num">Products</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong><br><small class="hint"><?= e((string) $c['description']) ?></small></td>
          <td><code><?= e($c['slug']) ?></code></td>
          <td class="num"><?= (int) $c['product_count'] ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-outline btn-sm" href="<?= e(url('admin/categories.php?id=' . (int) $c['id'])) ?>">Edit</a>
              <form class="inline-form" method="post" action="<?= e(url('admin/categories.php')) ?>" onsubmit="return confirm('Delete this category?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit" <?= (int) $c['product_count'] > 0 ? 'disabled title="Category still has products"' : '' ?>>Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
