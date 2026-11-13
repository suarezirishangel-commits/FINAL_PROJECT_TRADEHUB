<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$form   = [
    'full_name' => post('full_name'),
    'email'     => post('email'),
    'phone'     => post('phone'),
    'address'   => post('address'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (mb_strlen($form['full_name']) < 3) {
        $errors[] = 'Enter your full name (at least 3 characters).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($form['phone'] !== '' && !is_valid_phone($form['phone'])) {
        $errors[] = 'Enter a valid contact number.';
    }

    $password = post('password');
    $confirm  = post('confirm_password');
    if ($problem = password_problem($password)) {
        $errors[] = $problem;
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([strtolower($form['email'])]);
        if ($exists->fetch()) {
            $errors[] = 'An account with that email already exists. Try logging in instead.';
        }
    }

    if (!$errors) {
        $roleStmt = db()->prepare('SELECT id FROM roles WHERE name = "Customer"');
        $roleStmt->execute();
        $roleId = (int) $roleStmt->fetchColumn();

        $stmt = db()->prepare(
            'INSERT INTO users (role_id, full_name, email, phone, address, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, "active")'
        );
        $stmt->execute([
            $roleId,
            $form['full_name'],
            strtolower($form['email']),
            $form['phone'] !== '' ? $form['phone'] : null,
            $form['address'] !== '' ? $form['address'] : null,
            password_hash($password, PASSWORD_BCRYPT),
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id']    = (int) db()->lastInsertId();
        $_SESSION['created_at'] = time();

        flash('success', 'Welcome to TradeHub! Your account is ready.');
        redirect('index.php');
    }
}

$pageTitle = 'Create account';
$active    = '';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="sub">Buy from local sellers and track every order in one place.</p>

    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="120" autocomplete="name">
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
      </div>
      <div class="field">
        <label for="phone">Contact number <span class="hint">(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30" autocomplete="tel">
      </div>
      <div class="field">
        <label for="address">Delivery address <span class="hint">(optional)</span></label>
        <input type="text" id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" autocomplete="street-address">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <p class="hint">At least 8 characters, with one letter and one number.</p>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="auth-foot">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
