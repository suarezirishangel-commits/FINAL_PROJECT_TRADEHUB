<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$email  = post('email');

// Very small brute-force guard: 5 failures per 5 minutes per session.
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'until' => 0];
$locked   = $attempts['count'] >= 5 && time() < (int) $attempts['until'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($locked) {
        $wait    = (int) ceil(((int) $attempts['until'] - time()) / 60);
        $errors[] = "Too many failed attempts. Try again in about $wait minute(s).";
    } else {
        $password = post('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $errors[] = 'Enter your email and password.';
        } else {
            $stmt = db()->prepare(
                'SELECT u.id, u.password_hash, u.status, r.name AS role_name
                 FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.email = ?'
            );
            $stmt->execute([strtolower($email)]);
            $account = $stmt->fetch();

            if ($account && $account['status'] === 'active' && password_verify($password, $account['password_hash'])) {
                if (password_needs_rehash($account['password_hash'], PASSWORD_BCRYPT)) {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_BCRYPT), $account['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['user_id']    = (int) $account['id'];
                $_SESSION['created_at'] = time();
                unset($_SESSION['login_attempts']);

                flash('success', 'Welcome back!');
                redirect($account['role_name'] === 'Administrator' ? 'admin/index.php' : 'index.php');
            }

            $attempts['count'] = (int) $attempts['count'] + 1;
            $attempts['until'] = time() + 300;
            $_SESSION['login_attempts'] = $attempts;
            $errors[] = 'Incorrect email or password.';
        }
    }
}

$pageTitle = 'Log in';
$active    = '';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="sub">Log in to manage your cart, orders, and profile.</p>

    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" <?= $locked ? 'disabled' : '' ?>>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" <?= $locked ? 'disabled' : '' ?>>
      </div>
      <button class="btn btn-primary btn-block" type="submit" <?= $locked ? 'disabled' : '' ?>>Log in</button>
    </form>
    <p class="auth-foot">New to TradeHub? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
