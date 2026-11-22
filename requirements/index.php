<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$user = current_user();
$isBuyer = $user !== null && $user['role'] === 'buyer';
$requirements = db()->query(
    "SELECT requirements.id, requirements.product_name, requirements.quantity, requirements.fabric,
            requirements.delivery_date, requirements.created_at, requirements.status,
            users.name AS buyer_name, companies.name AS buyer_company
     FROM requirements
     INNER JOIN users ON users.id = requirements.buyer_id
     LEFT JOIN companies ON companies.owner_user_id = users.id
     WHERE requirements.status = 'open'
     ORDER BY requirements.created_at DESC LIMIT 100"
)->fetchAll();

$pageTitle = 'Buyer requirements';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Buyer sourcing requests</p><h1>Buyer requirements</h1>
        <p>Discover open garment sourcing requirements shared by buyers.</p>
        <?php if ($isBuyer): ?><a class="button" href="<?= e(app_url('requirements/create.php')) ?>">Post a requirement</a><?php endif; ?>
    </section>
    <section class="feature-grid">
        <?php foreach ($requirements as $requirement): ?>
            <article class="card">
                <p class="eyebrow"><?= e($requirement['buyer_company'] ?? $requirement['buyer_name']) ?></p>
                <h2><a href="<?= e(app_url('requirements/view.php?id=' . (int) $requirement['id'])) ?>"><?= e($requirement['product_name']) ?></a></h2>
                <p>Quantity: <?= number_format((int) $requirement['quantity']) ?> · Fabric: <?= e($requirement['fabric'] ?? 'Flexible') ?></p>
                <p>Delivery: <?= e($requirement['delivery_date'] ?? 'To be confirmed') ?></p>
                <a href="<?= e(app_url('requirements/view.php?id=' . (int) $requirement['id'])) ?>">View requirement</a>
            </article>
        <?php endforeach; ?>
        <?php if ($requirements === []): ?><article class="card"><p>No open requirements have been posted yet.</p></article><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
