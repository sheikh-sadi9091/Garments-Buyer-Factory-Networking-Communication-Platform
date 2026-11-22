<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }

    $notificationId = trim((string) ($_POST['notification_id'] ?? ''));
    if ($notificationId === 'all') {
        $statement = db()->prepare(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $user['id']]);
    } else {
        $id = filter_var($notificationId, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            http_response_code(400);
            exit('A valid notification is required.');
        }
        $statement = db()->prepare(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $id, 'user_id' => $user['id']]);
    }

    set_flash('success', 'Notification status updated.');
    redirect_to(app_url('notifications/index.php'));
}

$statement = db()->prepare(
    'SELECT id, type, title, body, target_url, read_at, created_at
     FROM notifications WHERE user_id = :user_id
     ORDER BY created_at DESC LIMIT 100'
);
$statement->execute(['user_id' => $user['id']]);
$notifications = $statement->fetchAll();

$pageTitle = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Updates</p><h1>Notifications</h1>
        <form method="post" action="<?= e(app_url('notifications/index.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="notification_id" value="all">
            <button class="button button-outline" type="submit">Mark all as read</button>
        </form>
    </section>
    <section class="card">
        <?php if ($notifications === []): ?><p>You have no notifications.</p>
        <?php else: ?><ul class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <li class="<?= $notification['read_at'] === null ? 'is-unread' : '' ?>">
                    <strong><?= e($notification['title']) ?></strong>
                    <p><?= e($notification['body'] ?? '') ?></p>
                    <small><?= e($notification['created_at']) ?> · <?= e(str_replace('_', ' ', $notification['type'])) ?></small>
                    <?php if ($notification['target_url'] !== null): ?><p><a href="<?= e(app_url(ltrim($notification['target_url'], '/'))) ?>">Open update</a></p><?php endif; ?>
                    <?php if ($notification['read_at'] === null): ?>
                        <form method="post" action="<?= e(app_url('notifications/index.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                            <button class="link-button" type="submit">Mark as read</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
