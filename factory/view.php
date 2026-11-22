<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house', 'factory', 'supplier', 'admin');

$companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($companyId === false || $companyId === null || $companyId < 1) {
    http_response_code(400);
    exit('A valid factory ID is required.');
}
$statement = db()->prepare(
    "SELECT companies.*, users.name AS contact_name
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE companies.id = :company_id AND roles.slug IN ('factory', 'supplier') LIMIT 1"
);
$statement->execute(['company_id' => $companyId]);
$factory = $statement->fetch();
if ($factory === false) {
    http_response_code(404);
    exit('Factory not found.');
}
$productsStatement = db()->prepare(
    "SELECT products.id, products.name, products.fabric, products.description, products.moq,
            products.price_min, products.price_max, products.currency, categories.name AS category_name
     FROM products INNER JOIN categories ON categories.id = products.category_id
     WHERE products.company_id = :company_id AND products.status = 'active'
     ORDER BY products.created_at DESC"
);
$productsStatement->execute(['company_id' => $companyId]);
$products = $productsStatement->fetchAll();
$certificatesStatement = db()->prepare(
    "SELECT certificate_name, expires_on FROM company_certificates
     WHERE company_id = :company_id AND status = 'approved'
     ORDER BY certificate_name"
);
$certificatesStatement->execute(['company_id' => $companyId]);
$certificates = $certificatesStatement->fetchAll();
$trustStatement = db()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM orders WHERE orders.factory_company_id = companies.id AND orders.status = \'completed\') AS completed_orders,
        (SELECT COUNT(*) FROM rfq_recipients WHERE rfq_recipients.recipient_company_id = companies.id) AS rfq_count,
        (SELECT COUNT(*) FROM quotations WHERE quotations.factory_company_id = companies.id AND quotations.status NOT IN (\'withdrawn\', \'rejected\')) AS quote_count,
        (SELECT AVG(reviews.rating) FROM reviews WHERE reviews.reviewee_id = companies.owner_user_id) AS average_rating,
        (SELECT COUNT(*) FROM reviews WHERE reviews.reviewee_id = companies.owner_user_id) AS review_count
     FROM companies WHERE companies.id = :company_id'
);
$trustStatement->execute(['company_id' => $companyId]);
$trust = $trustStatement->fetch();
$responseRate = (int) $trust['rfq_count'] > 0
    ? round(((int) $trust['quote_count'] / (int) $trust['rfq_count']) * 100)
    : null;

$pageTitle = $factory['name'];
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Factory profile · <?= e($factory['location']) ?></p>
        <?php if ($factory['cover_image_path'] !== null): ?><img class="company-cover" src="<?= e(app_url($factory['cover_image_path'])) ?>" alt="<?= e($factory['name']) ?> company cover"><?php endif; ?>
        <?php if ($factory['logo_path'] !== null): ?><img class="company-logo" src="<?= e(app_url($factory['logo_path'])) ?>" alt="<?= e($factory['name']) ?> logo"><?php endif; ?>
        <h1><?= e($factory['name']) ?></h1>
        <p><span class="status-pill status-<?= e($factory['verification_status']) ?>"><?= e(ucfirst($factory['verification_status'])) ?> account</span></p>
        <p><?= e($factory['description'] ?? 'No company description provided.') ?></p>
        <p>Capacity: <?= $factory['capacity_per_month'] === null ? 'Not provided' : number_format((int) $factory['capacity_per_month']) . ' units/month' ?> ·
            MOQ: <?= $factory['moq'] === null ? 'Not provided' : number_format((int) $factory['moq']) ?> ·
            Lead time: <?= $factory['lead_time_days'] === null ? 'Not provided' : (int) $factory['lead_time_days'] . ' days' ?></p>
        <?php if ($factory['website'] !== null): ?><p>Website: <a href="<?= e($factory['website']) ?>" rel="noopener noreferrer" target="_blank"><?= e($factory['website']) ?></a></p><?php endif; ?>
        <p>Completed orders: <?= (int) $trust['completed_orders'] ?> ·
            RFQ response rate: <?= $responseRate === null ? 'New profile' : (int) $responseRate . '%' ?> ·
            Rating: <?= $trust['average_rating'] === null ? 'No reviews yet' : e(number_format((float) $trust['average_rating'], 1) . '/5 (' . (int) $trust['review_count'] . ' reviews)') ?></p>
    </section>
    <section class="admin-section"><h2>Approved certifications</h2>
        <?php if ($certificates === []): ?><p>No approved certificates have been listed.</p>
        <?php else: ?><ul><?php foreach ($certificates as $certificate): ?><li><?= e($certificate['certificate_name']) ?><?php if ($certificate['expires_on'] !== null): ?> · expires <?= e($certificate['expires_on']) ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <section class="admin-section"><h2>Products</h2><div class="feature-grid">
        <?php foreach ($products as $product): ?>
            <article class="card"><p class="eyebrow"><?= e($product['category_name']) ?></p>
                <h3><a href="<?= e(app_url('products/view.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a></h3>
                <p><?= e($product['fabric'] ?? 'Fabric not specified') ?> · MOQ <?= number_format((int) $product['moq']) ?></p>
                <p><?= e(money_format_value($product['price_min'], $product['currency'])) ?>–<?= e(money_format_value($product['price_max'], $product['currency'])) ?></p>
            </article>
        <?php endforeach; ?>
        <?php if ($products === []): ?><article class="card"><p>No active products listed.</p></article><?php endif; ?>
    </div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
