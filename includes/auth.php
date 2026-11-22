<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    static $checked = false;
    static $cachedUser = null;
    if ($checked) {
        return $cachedUser;
    }
    $checked = true;

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT users.id, users.name, users.email, users.status, roles.slug AS role
         FROM users INNER JOIN roles ON roles.id = users.role_id
         WHERE users.id = :user_id LIMIT 1'
    );
    $statement->execute(['user_id' => $_SESSION['user']['id']]);
    $user = $statement->fetch();
    if ($user === false || $user['status'] !== 'active') {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        set_flash('error', 'Your account is unavailable. Please contact the platform administrator.');
        return null;
    }

    unset($user['status']);
    $_SESSION['user'] = $user;
    $cachedUser = $user;
    return $cachedUser;
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('error', 'Please sign in to continue.');
        redirect_to(app_url('auth/login.php'));
    }
}

function require_role(string ...$roles): void
{
    require_login();
    $user = current_user();
    if ($user === null || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}
