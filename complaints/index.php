<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$statement = db()->prepare(
    'SELECT complaints.id, complaints.subject, complaints.description, complaints.status,
            complaints.resolution, complaints.created_at
     FROM complaints WHERE reporter_id = :user_id ORDER BY created_at DESC LIMIT 100'
);
$statement->execute(['user_id' => current_user()['id']]);
$complaints = $statement->fetchAll();

$pageTitle = 'My complaints';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Help and safety</p><h1>My complaints</h1><p>Review reports you sent to the platform team.</p><a class="button" href="<?= e(app_url('complaints/create.php')) ?>">Report an issue</a></section>
    <section class="feature-grid"><?php foreach ($complaints as $complaint): ?><article class="card">
        <p class="eyebrow"><?= e($complaint['created_at']) ?> · <?= e(ucfirst($complaint['status'])) ?></p><h2><?= e($complaint['subject']) ?></h2>
        <p><?= nl2br(e($complaint['description'])) ?></p><?php if ($complaint['resolution'] !== null): ?><p><strong>Admin response:</strong> <?= nl2br(e($complaint['resolution'])) ?></p><?php endif; ?>
    </article><?php endforeach; ?>
    <?php if ($complaints === []): ?><article class="card"><p>You have not submitted any complaints.</p></article><?php endif; ?></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
