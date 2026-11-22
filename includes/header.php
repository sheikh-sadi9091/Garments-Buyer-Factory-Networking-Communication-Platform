<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$pageTitle = isset($pageTitle) ? $pageTitle . ' | Garments Network' : 'Garments Network';
$signedInUser = current_user();
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    <script defer src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(app_url('index.php')) ?>">Garments<span>Network</span></a>
        <nav aria-label="Main navigation">
            <a href="<?= e(app_url('index.php')) ?>">Home</a>
            <a href="<?= e(app_url('about.php')) ?>">About</a>
            <a href="<?= e(app_url('events/index.php')) ?>">Events</a>
            <a href="<?= e(app_url('news/index.php')) ?>">News</a>
            <?php if ($signedInUser !== null): ?>
                <a href="<?= e(app_url('dashboard.php')) ?>">Dashboard</a>
                <?php if ($signedInUser['role'] === 'admin'): ?>
                    <a href="<?= e(app_url('admin/index.php')) ?>">Admin panel</a>
                <?php elseif ($signedInUser['role'] === 'buying_house'): ?>
                    <a href="<?= e(app_url('factory/index.php')) ?>">Factory directory</a>
                    <a href="<?= e(app_url('buying_house/network.php')) ?>">My factory network</a>
                    <a href="<?= e(app_url('buying_house/rfqs.php')) ?>">Buyer RFQs</a>
                    <a href="<?= e(app_url('products/index.php')) ?>">Products</a>
                    <a href="<?= e(app_url('messages/index.php')) ?>">Messages</a>
                <?php elseif (in_array($signedInUser['role'], ['factory', 'supplier'], true)): ?>
                    <a href="<?= e(app_url('companies/profile.php')) ?>">Company</a>
                    <a href="<?= e(app_url('factory/products.php')) ?>">Products</a>
                    <a href="<?= e(app_url('factory/rfqs.php')) ?>">RFQs</a>
                    <a href="<?= e(app_url('factory/samples.php')) ?>">Samples</a>
                    <a href="<?= e(app_url('factory/orders.php')) ?>">Orders</a>
                    <a href="<?= e(app_url('messages/index.php')) ?>">Messages</a>
                <?php elseif ($signedInUser['role'] === 'buyer'): ?>
                    <a href="<?= e(app_url('buying_houses/index.php')) ?>">Buying houses</a>
                    <a href="<?= e(app_url('requirements/index.php')) ?>">Requirements</a>
                    <a href="<?= e(app_url('buyer/rfqs.php')) ?>">My RFQs</a>
                    <a href="<?= e(app_url('buyer/orders.php')) ?>">Orders</a>
                    <a href="<?= e(app_url('messages/index.php')) ?>">Messages</a>
                <?php endif; ?>
                <?php
                $unreadStatement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
                $unreadStatement->execute(['user_id' => $signedInUser['id']]);
                $unreadCount = (int) $unreadStatement->fetchColumn();
                ?>
                <a href="<?= e(app_url('notifications/index.php')) ?>">Notifications<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a>
                <a href="<?= e(app_url('complaints/index.php')) ?>">Help</a>
                <span class="nav-user"><?= e($signedInUser['name']) ?></span>
                <form class="nav-form" method="post" action="<?= e(app_url('auth/logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button class="link-button" type="submit">Log out</button>
                </form>
            <?php else: ?>
                <a href="<?= e(app_url('auth/login.php')) ?>">Log in</a>
                <a class="nav-cta" href="<?= e(app_url('auth/register.php')) ?>">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($flashMessage !== null): ?>
    <div class="container">
        <div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
    </div>
<?php endif; ?>
