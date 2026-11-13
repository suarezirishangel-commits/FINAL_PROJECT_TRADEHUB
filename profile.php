<?php
require __DIR__ . '/includes/bootstrap.php';

require_login();
$user   = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'profile') {
        $fullName = post('full_name');
        $phone    = post('phone');
        $address  = post('address');

        if (mb_strlen($fullName) < 3) {
            $errors[] = 'Enter your full name (at least 3 characters).';
        }
        if ($phone !== '' && !is_valid_phone($phone)) {
            $errors[] = 'Enter a valid contact number.';
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?')
                ->execute([$fullName, $phone !== '' ? $phone : null, $address !== '' ? $address : null, $user['id']]);
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $current = post('current_password');
        $new     = post('new_password');
        $confirm = post('confirm_password');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif ($problem = password_problem($new)) {
            $errors[] = $problem;
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My profile';
$active    = '';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head">
    <div class="kicker">Account</div>
    <h1>My profile</h1>
    <p>Update your delivery details and password.</p>
  </div>

  <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="cart-layout">
    <form class="panel" method="post" action="<?= e(url('profile.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <h2>Account details</h2>
      <div class="field">
        <label>Email address</label>
        <input type="email" value="<?= e($user['email']) ?>" disabled>
      </div>
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" required maxlength="120">
      </div>
      <div class="field">
        <label for="phone">Contact number</label>
        <input type="tel" id="phone" name="phone" value="<?= e((string) $user['phone']) ?>" maxlength="30">
      </div>
      <div class="field">
        <label for="address">Delivery address</label>
        <input type="text" id="address" name="address" value="<?= e((string) $user['address']) ?>" maxlength="255">
      </div>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>

    <form class="panel" method="post" action="<?= e(url('profile.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h2>Change password</h2>
      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>
      <p class="hint">At least 8 characters, with one letter and one number.</p>
      <button class="btn btn-outline" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
