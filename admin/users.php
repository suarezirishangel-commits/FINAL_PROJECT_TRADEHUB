<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$adminTitle  = 'Users';
$adminActive = 'users.php';
$me          = current_user();
$roles       = db()->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id     = (int) post('id');
    $action = post('action');

    if ($id === (int) $me['id']) {
        flash('error', 'You cannot change your own role or status.');
        redirect('admin/users.php');
    }

    if ($action === 'role') {
        $roleId = (int) post('role_id');
        $valid  = false;
        foreach ($roles as $role) {
            if ((int) $role['id'] === $roleId) {
                $valid = true;
            }
        }
        if ($valid) {
            db()->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $id]);
            flash('success', 'User role updated.');
        } else {
            flash('error', 'Invalid role selected.');
        }
    } elseif ($action === 'status') {
        db()->prepare('UPDATE users SET status = IF(status = "active", "inactive", "active") WHERE id = ?')->execute([$id]);
        flash('success', 'User status updated.');
    }

    redirect('admin/users.php');
}

$users = db()->query(
    'SELECT u.*, r.name AS role_name,
        (SELECT COUNT(DISTINCT t.reference) FROM transactions t WHERE t.user_id = u.id) AS order_count,
        (SELECT COALESCE(SUM(t.total_amount), 0) FROM transactions t WHERE t.user_id = u.id AND t.status <> "cancelled") AS spent
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="admin-topbar">
  <div><h1>Users</h1><p><?= count($users) ?> accounts. Administrators manage the marketplace; customers buy from it.</p></div>
</div>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>User</th><th>Contact</th><th>Role</th><th class="num">Orders</th><th class="num">Spent</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['full_name']) ?></strong><br><small class="hint"><?= e($u['email']) ?></small></td>
          <td><?= e((string) ($u['phone'] ?: '—')) ?><br><small class="hint"><?= e((string) ($u['address'] ?: '')) ?></small></td>
          <td>
            <form class="inline-form" method="post" action="<?= e(url('admin/users.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="role">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <select name="role_id" onchange="this.form.submit()" <?= (int) $u['id'] === (int) $me['id'] ? 'disabled' : '' ?>>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= (int) $role['id'] ?>" <?= (int) $u['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="num"><?= (int) $u['order_count'] ?></td>
          <td class="num"><?= format_price($u['spent']) ?></td>
          <td><span class="badge <?= $u['status'] === 'active' ? 'badge-in' : 'badge-out' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
          <td>
            <form class="inline-form" method="post" action="<?= e(url('admin/users.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit" <?= (int) $u['id'] === (int) $me['id'] ? 'disabled' : '' ?>>
                <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
