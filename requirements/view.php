<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';

$requirementId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($requirementId === false || $requirementId === null || $requirementId < 1) {
    http_response_code(400);
    exit('A valid requirement ID is required.');
}
$statement = db()->prepare(
    'SELECT requirements.*, users.name AS buyer_name, companies.name AS buyer_company
     FROM requirements
     INNER JOIN users ON users.id = requirements.buyer_id
     LEFT JOIN companies ON companies.owner_user_id = users.id
     WHERE requirements.id = :requirement_id LIMIT 1'
);
$statement->execute(['requirement_id' => $requirementId]);
$requirement = $statement->fetch();
if ($requirement === false) {
    http_response_code(404);
    exit('Requirement not found.');
}
$user = current_user();
$isOwner = $user !== null && (int) $user['id'] === (int) $requirement['buyer_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isOwner) {
        http_response_code(403);
        exit('Only the requirement owner can update it.');
    }
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }
    if ($requirement['status'] !== 'open') {
        http_response_code(409);
        exit('This requirement is already closed.');
    }
    $close = db()->prepare(
        "UPDATE requirements SET status = 'closed'
         WHERE id = :requirement_id AND buyer_id = :buyer_id AND status = 'open'"
    );
    $close->execute(['requirement_id' => $requirementId, 'buyer_id' => $user['id']]);
    if ($close->rowCount() !== 1) {
        http_response_code(409);
        exit('The requirement could not be closed. Reload and try again.');
    }
    set_flash('success', 'Requirement closed.');
    redirect_to(app_url('requirements/view.php?id=' . (int) $requirementId));
}
if ($requirement['status'] !== 'open' && !$isOwner) {
    http_response_code(404);
    exit('Requirement not found.');
}

$pageTitle = $requirement['product_name'];
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow"><?= e($requirement['buyer_company'] ?? $requirement['buyer_name']) ?> · <?= e(ucfirst($requirement['status'])) ?></p>
        <h1><?= e($requirement['product_name']) ?></h1>
        <p>Requested quantity: <?= number_format((int) $requirement['quantity']) ?> units</p>
    </section>
    <section class="card">
        <h2>Requirement details</h2>
        <p>Fabric: <?= e($requirement['fabric'] ?? 'Flexible') ?></p>
        <p>Target unit price: <?= e(money_format_value($requirement['target_price'], $requirement['currency'])) ?></p>
        <p>Delivery date: <?= e($requirement['delivery_date'] ?? 'To be confirmed') ?></p>
        <p>Certifications: <?= e($requirement['certification_needed'] ?? 'No specific certification requested') ?></p>
        <p><?= nl2br(e($requirement['details'] ?? 'No additional notes provided.')) ?></p>
        <p>Posted <?= e($requirement['created_at']) ?></p>
        <?php if ($isOwner && $requirement['status'] === 'open'): ?>
            <a class="button button-outline" href="<?= e(app_url('rfqs/create.php?requirement_id=' . (int) $requirement['id'])) ?>">Create and send RFQ</a>
            <form method="post" action="<?= e(app_url('requirements/view.php?id=' . (int) $requirement['id'])) ?>" class="inline-form">
                <?= csrf_field() ?><button class="link-button" type="submit">Close requirement</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
