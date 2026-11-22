<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (current_user() !== null) {
    redirect_to(app_url('dashboard.php'));
}

$errors = [];
$values = ['name' => '', 'email' => '', 'role' => '', 'company_name' => '', 'location' => ''];
$allowedRoles = ['buyer', 'buying_house', 'factory', 'supplier'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        foreach ($values as $key => $_) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($values['name'] === '' || strlen($values['name']) > 120) {
            $errors[] = 'Enter your name (up to 120 characters).';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($values['role'], $allowedRoles, true)) {
            $errors[] = 'Select a valid account type.';
        }
        if ($values['company_name'] === '' || strlen($values['company_name']) > 190) {
            $errors[] = 'Enter your company name (up to 190 characters).';
        }
        if ($values['location'] === '' || strlen($values['location']) > 190) {
            $errors[] = 'Enter your company location (up to 190 characters).';
        }
        if (strlen($password) < 10 || strlen($password) > 255) {
            $errors[] = 'Use a password between 10 and 255 characters.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'The password confirmation does not match.';
        }

        if ($errors === []) {
            $pdo = db();
            $roleStatement = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
            $roleStatement->execute(['slug' => $values['role']]);
            $roleId = $roleStatement->fetchColumn();
            if ($roleId === false) {
                $errors[] = 'That account type is not available.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $userStatement = $pdo->prepare(
                        'INSERT INTO users (role_id, name, email, password_hash) VALUES (:role_id, :name, :email, :password_hash)'
                    );
                    $userStatement->execute([
                        'role_id' => $roleId,
                        'name' => $values['name'],
                        'email' => strtolower($values['email']),
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $userId = (int) $pdo->lastInsertId();
                    $companyStatement = $pdo->prepare(
                        'INSERT INTO companies (owner_user_id, name, location) VALUES (:owner_user_id, :name, :location)'
                    );
                    $companyStatement->execute([
                        'owner_user_id' => $userId,
                        'name' => $values['company_name'],
                        'location' => $values['location'],
                    ]);
                    $pdo->commit();

                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id' => $userId,
                        'name' => $values['name'],
                        'email' => strtolower($values['email']),
                        'role' => $values['role'],
                    ];
                    set_flash('success', 'Your account has been created.');
                    redirect_to(app_url('dashboard.php'));
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($exception->getCode() === '23000') {
                        $errors[] = 'An account with that email address already exists.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card">
        <p class="eyebrow">Join the network</p>
        <h1>Create an account</h1>
        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(app_url('auth/register.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <label for="name">Your name</label>
            <input id="name" name="name" maxlength="120" autocomplete="name" required value="<?= e($values['name']) ?>">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e($values['email']) ?>">
            <label for="role">Account type</label>
            <select id="role" name="role" required>
                <option value="">Select an account type</option>
                <option value="buyer" <?= $values['role'] === 'buyer' ? 'selected' : '' ?>>Buyer — work with buying houses</option>
                <option value="buying_house" <?= $values['role'] === 'buying_house' ? 'selected' : '' ?>>Buying house — manage factory network</option>
                <option value="factory" <?= $values['role'] === 'factory' ? 'selected' : '' ?>>Factory — connect through buying houses</option>
                <option value="supplier" <?= $values['role'] === 'supplier' ? 'selected' : '' ?>>Supplier</option>
            </select>
            <label for="company_name">Company name</label>
            <input id="company_name" name="company_name" maxlength="190" autocomplete="organization" required value="<?= e($values['company_name']) ?>">
            <label for="location">Company location</label>
            <input id="location" name="location" maxlength="190" placeholder="e.g. Gazipur, Bangladesh" required value="<?= e($values['location']) ?>">
            <label for="password">Password (at least 10 characters)</label>
            <input id="password" name="password" type="password" minlength="10" maxlength="255" autocomplete="new-password" required>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="10" maxlength="255" autocomplete="new-password" required>
            <button class="button button-full" type="submit">Create account</button>
        </form>
        <p class="form-note">Already registered? <a href="<?= e(app_url('auth/login.php')) ?>">Log in</a>.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
