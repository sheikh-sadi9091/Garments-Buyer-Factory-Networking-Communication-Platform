<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (current_user() !== null) {
    redirect_to(app_url('dashboard.php'));
}

$errors = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors[] = 'Enter your password.';
        }

        if ($errors === []) {
            $statement = db()->prepare(
                'SELECT users.id, users.name, users.email, users.password_hash, users.status, roles.slug AS role
                 FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
            if ($user === false || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'The email or password was not recognized.';
            } else {
                session_regenerate_id(true);
                unset($user['password_hash'], $user['status']);
                $_SESSION['user'] = $user;
                set_flash('success', 'You are now signed in.');
                redirect_to(app_url('dashboard.php'));
            }
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card">
        <p class="eyebrow">Welcome back</p>
        <h1>Log in</h1>
        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(app_url('auth/login.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e($email) ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="button button-full" type="submit">Log in</button>
        </form>
        <p class="form-note">New to the platform? <a href="<?= e(app_url('auth/register.php')) ?>">Create an account</a>.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
