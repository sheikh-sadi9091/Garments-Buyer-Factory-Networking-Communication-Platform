<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$events = db()->query(
    "SELECT id, title, description, venue, location, starts_at, ends_at, registration_url
     FROM events WHERE status = 'published' AND starts_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
     ORDER BY starts_at ASC LIMIT 50"
)->fetchAll();

$pageTitle = 'Events';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Industry calendar</p><h1>Events</h1><p>Meet sourcing partners and stay connected with the Bangladesh apparel industry.</p></section>
    <section class="feature-grid">
        <?php foreach ($events as $event): ?><article class="card">
            <p class="eyebrow"><?= e(date('M j, Y · g:i A', strtotime($event['starts_at']))) ?></p>
            <h2><?= e($event['title']) ?></h2>
            <p><?= e($event['location'] ?? '') ?><?php if ($event['venue'] !== null): ?> · <?= e($event['venue']) ?><?php endif; ?></p>
            <p><?= nl2br(e($event['description'] ?? '')) ?></p>
            <?php if ($event['registration_url'] !== null): ?><a class="button button-outline" href="<?= e($event['registration_url']) ?>" rel="noopener noreferrer" target="_blank">Register for event</a><?php endif; ?>
        </article><?php endforeach; ?>
        <?php if ($events === []): ?><article class="card"><p>No upcoming events are listed.</p></article><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
