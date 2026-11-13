<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$wordpressConfigured = wordpress_cms_configured();
if ($wordpressConfigured) {
    $wordpressAdminUrl = rtrim((string) config()['cms']['wordpress_url'], '/') . '/wp-admin/options-general.php?page=tradehub-content';
    $adminTitle  = 'WordPress content';
    $adminActive = 'content.php';
    require __DIR__ . '/_header.php';
    ?>
    <div class="admin-topbar">
      <div><h1>WordPress content management</h1><p>WordPress na ang source ng homepage copy at announcements ng storefront.</p></div>
    </div>
    <section class="card">
      <div class="card-head"><h2>Buksan ang WordPress CMS</h2></div>
      <p>Gamitin ang WordPress dashboard para baguhin ang site name, tagline, homepage text, contact details, at announcements.</p>
      <a class="btn btn-primary" href="<?= e($wordpressAdminUrl) ?>" target="_blank" rel="noopener noreferrer">Manage content in WordPress</a>
    </section>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$adminTitle  = 'Content & announcements';
$adminActive = 'content.php';
$errors      = [];

$allowedSettings = [
    'site_name', 'tagline', 'hero_title', 'hero_text', 'about_text',
    'contact_email', 'contact_phone', 'commission_rate',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'settings') {
        $values = [];
        foreach ($allowedSettings as $key) {
            $values[$key] = post($key);
        }

        if (mb_strlen($values['site_name']) < 2) {
            $errors[] = 'Site name is required.';
        }
        if (!filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid contact email.';
        }
        if (!is_numeric($values['commission_rate']) || (float) $values['commission_rate'] < 0 || (float) $values['commission_rate'] > 100) {
            $errors[] = 'Commission rate must be between 0 and 100.';
        }

        if (!$errors) {
            $stmt = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            flash('success', 'Website content updated.');
            redirect('admin/content.php');
        }
    } elseif ($action === 'announcement_save') {
        $id    = (int) post('id');
        $title = post('title');
        $body  = post('body');
        $live  = isset($_POST['is_published']) ? 1 : 0;

        if (mb_strlen($title) < 3) {
            $errors[] = 'Announcement title is too short.';
        }
        if (mb_strlen($body) < 5) {
            $errors[] = 'Announcement body is too short.';
        }

        if (!$errors) {
            if ($id > 0) {
                db()->prepare('UPDATE announcements SET title = ?, body = ?, is_published = ? WHERE id = ?')
                    ->execute([$title, $body, $live, $id]);
                flash('success', 'Announcement updated.');
            } else {
                db()->prepare('INSERT INTO announcements (title, body, is_published) VALUES (?, ?, ?)')
                    ->execute([$title, $body, $live]);
                flash('success', 'Announcement published.');
            }
            redirect('admin/content.php');
        }
    } elseif ($action === 'announcement_delete') {
        db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int) post('id')]);
        flash('success', 'Announcement deleted.');
        redirect('admin/content.php');
    } elseif ($action === 'announcement_toggle') {
        db()->prepare('UPDATE announcements SET is_published = 1 - is_published WHERE id = ?')->execute([(int) post('id')]);
        flash('success', 'Announcement visibility changed.');
        redirect('admin/content.php');
    }
}

$editingAnnouncement = null;
if (isset($_GET['announcement'])) {
    $stmt = db()->prepare('SELECT * FROM announcements WHERE id = ?');
    $stmt->execute([(int) $_GET['announcement']]);
    $editingAnnouncement = $stmt->fetch() ?: null;
}

$announcements = db()->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Content &amp; announcements</h1><p>Edit storefront copy and publish marketplace notices.</p></div>
</div>

<?php if ($errors): ?>
  <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Website content</h2></div>
    <form method="post" action="<?= e(url('admin/content.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="settings">
      <div class="form-grid">
        <div class="field">
          <label for="site_name">Site name</label>
          <input type="text" id="site_name" name="site_name" value="<?= e(setting('site_name')) ?>" required maxlength="60">
        </div>
        <div class="field">
          <label for="tagline">Tagline</label>
          <input type="text" id="tagline" name="tagline" value="<?= e(setting('tagline')) ?>" maxlength="140">
        </div>
        <div class="field span-2">
          <label for="hero_title">Home headline</label>
          <input type="text" id="hero_title" name="hero_title" value="<?= e(setting('hero_title')) ?>" maxlength="140">
        </div>
        <div class="field span-2">
          <label for="hero_text">Home introduction</label>
          <textarea id="hero_text" name="hero_text" maxlength="500"><?= e(setting('hero_text')) ?></textarea>
        </div>
        <div class="field span-2">
          <label for="about_text">Business overview</label>
          <textarea id="about_text" name="about_text" maxlength="800"><?= e(setting('about_text')) ?></textarea>
        </div>
        <div class="field">
          <label for="contact_email">Contact email</label>
          <input type="email" id="contact_email" name="contact_email" value="<?= e(setting('contact_email')) ?>" required>
        </div>
        <div class="field">
          <label for="contact_phone">Contact phone</label>
          <input type="text" id="contact_phone" name="contact_phone" value="<?= e(setting('contact_phone')) ?>" maxlength="30">
        </div>
        <div class="field">
          <label for="commission_rate">Commission rate (%)</label>
          <input type="number" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" value="<?= e(setting('commission_rate')) ?>" required>
          <p class="hint">Used for the commission totals in sales reports.</p>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save content</button>
    </form>
  </section>

  <div>
    <section class="card" style="margin-bottom:1.25rem">
      <div class="card-head"><h2><?= $editingAnnouncement ? 'Edit announcement' : 'New announcement' ?></h2>
        <?php if ($editingAnnouncement): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/content.php')) ?>">Cancel</a><?php endif; ?>
      </div>
      <form method="post" action="<?= e(url('admin/content.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="announcement_save">
        <input type="hidden" name="id" value="<?= (int) ($editingAnnouncement['id'] ?? 0) ?>">
        <div class="field">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" value="<?= e((string) ($editingAnnouncement['title'] ?? '')) ?>" required maxlength="150">
        </div>
        <div class="field">
          <label for="body">Message</label>
          <textarea id="body" name="body" maxlength="1000" required><?= e((string) ($editingAnnouncement['body'] ?? '')) ?></textarea>
        </div>
        <label class="checkline">
          <input type="checkbox" name="is_published" value="1" <?= (int) ($editingAnnouncement['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
          Publish on the storefront
        </label>
        <button class="btn btn-primary" type="submit"><?= $editingAnnouncement ? 'Save announcement' : 'Publish announcement' ?></button>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2>All announcements</h2></div>
      <?php if (!$announcements): ?>
        <p class="hint">No announcements yet.</p>
      <?php else: ?>
        <ul style="list-style:none;padding:0;margin:0">
          <?php foreach ($announcements as $a): ?>
            <li style="padding:.7rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
              <strong><?= e($a['title']) ?></strong><br>
              <small class="hint"><?= e(date('M j, Y', strtotime($a['created_at']))) ?> ·
                <span class="badge <?= (int) $a['is_published'] ? 'badge-in' : 'badge-out' ?>"><?= (int) $a['is_published'] ? 'Published' : 'Draft' ?></span>
              </small>
              <div class="row-actions" style="margin-top:.5rem">
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/content.php?announcement=' . (int) $a['id'])) ?>">Edit</a>
                <form class="inline-form" method="post" action="<?= e(url('admin/content.php')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="action" value="announcement_toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $a['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                </form>
                <form class="inline-form" method="post" action="<?= e(url('admin/content.php')) ?>" onsubmit="return confirm('Delete this announcement?');">
                  <?= csrf_field() ?><input type="hidden" name="action" value="announcement_delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
