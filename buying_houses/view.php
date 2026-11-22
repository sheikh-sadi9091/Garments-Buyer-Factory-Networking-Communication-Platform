<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($companyId === false || $companyId === null || $companyId < 1) {
    http_response_code(400);
    exit('A valid buying-house ID is required.');
}
$statement = db()->prepare(
    "SELECT companies.*, users.name AS contact_name
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE companies.id = :company_id AND roles.slug = 'buying_house'
     LIMIT 1"
);
$statement->execute(['company_id' => $companyId]);
$buyingHouse = $statement->fetch();
if ($buyingHouse === false) {
    http_response_code(404);
    exit('Buying house not found.');
}
$networkCount = db()->prepare(
    'SELECT COUNT(*) FROM buying_house_factories WHERE buying_house_company_id = :company_id'
);
$networkCount->execute(['company_id' => $companyId]);

$pageTitle = $buyingHouse['name'];
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Buying house · <?= e($buyingHouse['location']) ?></p>
        <?php if ($buyingHouse['cover_image_path'] !== null): ?><img class="company-cover" src="<?= e(app_url($buyingHouse['cover_image_path'])) ?>" alt="<?= e($buyingHouse['name']) ?> cover"><?php endif; ?>
        <?php if ($buyingHouse['logo_path'] !== null): ?><img class="company-logo" src="<?= e(app_url($buyingHouse['logo_path'])) ?>" alt="<?= e($buyingHouse['name']) ?> logo"><?php endif; ?>
        <h1><?= e($buyingHouse['name']) ?></h1>
        <p><span class="status-pill status-<?= e($buyingHouse['verification_status']) ?>"><?= e(ucfirst($buyingHouse['verification_status'])) ?> account</span></p>
        <p><?= e($buyingHouse['description'] ?? 'No company description provided.') ?></p>
        <p><?= (int) $networkCount->fetchColumn() ?> connected factories. Factory partners are coordinated through the buying house.</p>
        <?php if ($buyingHouse['website'] !== null): ?><p>Website: <a href="<?= e($buyingHouse['website']) ?>" rel="noopener noreferrer" target="_blank"><?= e($buyingHouse['website']) ?></a></p><?php endif; ?>
    </section>
    <section class="card">
        <h2>Work with this buying house</h2>
        <p>Post your product and delivery requirements, then choose this buying house when you send an RFQ. It will coordinate factory responses for you.</p>
        <a class="button" href="<?= e(app_url('requirements/create.php')) ?>">Post a requirement</a>
        <a class="button button-outline" href="<?= e(app_url('requirements/index.php')) ?>">View my requirements</a>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>