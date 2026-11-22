<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house', 'admin');

$filters = [
    'query' => trim((string) ($_GET['q'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'product' => trim((string) ($_GET['product'] ?? '')),
    'certification' => trim((string) ($_GET['certification'] ?? '')),
    'min_capacity' => trim((string) ($_GET['min_capacity'] ?? '')),
    'max_moq' => trim((string) ($_GET['max_moq'] ?? '')),
    'max_lead_time' => trim((string) ($_GET['max_lead_time'] ?? '')),
];
$where = ["roles.slug = 'factory'"];
$parameters = [];
if ($filters['query'] !== '') {
    $where[] = '(companies.name LIKE :company_name OR companies.description LIKE :company_description)';
    $parameters['company_name'] = '%' . $filters['query'] . '%';
    $parameters['company_description'] = '%' . $filters['query'] . '%';
}
if ($filters['location'] !== '') {
    $where[] = 'companies.location LIKE :location';
    $parameters['location'] = '%' . $filters['location'] . '%';
}
if ($filters['product'] !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM products p WHERE p.company_id = companies.id AND p.status = \'active\' AND p.name LIKE :product)';
    $parameters['product'] = '%' . $filters['product'] . '%';
}
if ($filters['certification'] !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM company_certificates cert WHERE cert.company_id = companies.id AND cert.status = \'approved\' AND cert.certificate_name LIKE :certification)';
    $parameters['certification'] = '%' . $filters['certification'] . '%';
}
$numericFilters = [
    'min_capacity' => 'companies.capacity_per_month >= :min_capacity',
    'max_moq' => 'companies.moq <= :max_moq',
    'max_lead_time' => 'companies.lead_time_days <= :max_lead_time',
];
foreach ($numericFilters as $key => $condition) {
    if ($filters[$key] !== '') {
        if (!ctype_digit($filters[$key])) {
            http_response_code(400);
            exit('Capacity, MOQ, and lead-time filters must be whole numbers.');
        }
        $where[] = $condition;
        $parameters[$key] = (int) $filters[$key];
    }
}

$statement = db()->prepare(
    'SELECT companies.id, companies.name, companies.location, companies.description,
            companies.capacity_per_month, companies.moq, companies.lead_time_days,
            companies.verification_status,
            (SELECT COUNT(*) FROM products p WHERE p.company_id = companies.id AND p.status = \'active\') AS product_count,
            (SELECT GROUP_CONCAT(DISTINCT cert.certificate_name ORDER BY cert.certificate_name SEPARATOR \', \')
             FROM company_certificates cert WHERE cert.company_id = companies.id AND cert.status = \'approved\') AS certifications
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY companies.verification_status = \'verified\' DESC, companies.name ASC
     LIMIT 100'
);
$statement->execute($parameters);
$factories = $statement->fetchAll();

$pageTitle = 'Factory directory';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Buying-house workspace</p><h1>Factory directory</h1><p>Buying houses use this directory to discover factories and build their partner networks. Buyers connect through buying houses.</p></section>
    <section class="card admin-section">
        <form class="filter-form" method="get" action="<?= e(app_url('factory/index.php')) ?>">
            <label>Company or keyword<input name="q" value="<?= e($filters['query']) ?>" maxlength="120"></label>
            <label>Location<input name="location" value="<?= e($filters['location']) ?>" placeholder="Dhaka, Gazipur..." maxlength="120"></label>
            <label>Product<input name="product" value="<?= e($filters['product']) ?>" maxlength="120"></label>
            <label>Certification<input name="certification" value="<?= e($filters['certification']) ?>" maxlength="120"></label>
            <label>Minimum capacity<input type="number" name="min_capacity" min="0" value="<?= e($filters['min_capacity']) ?>"></label>
            <label>Maximum MOQ<input type="number" name="max_moq" min="0" value="<?= e($filters['max_moq']) ?>"></label>
            <label>Maximum lead time (days)<input type="number" name="max_lead_time" min="0" value="<?= e($filters['max_lead_time']) ?>"></label>
            <button class="button" type="submit">Search</button>
        </form>
    </section>
    <section class="feature-grid">
        <?php foreach ($factories as $factory): ?>
            <article class="card">
                <p><span class="status-pill status-<?= e($factory['verification_status']) ?>"><?= e(ucfirst($factory['verification_status'])) ?></span></p>
                <h2><a href="<?= e(app_url('factory/view.php?id=' . (int) $factory['id'])) ?>"><?= e($factory['name']) ?></a></h2>
                <p><?= e($factory['location']) ?></p>
                <p><?= e($factory['description'] ?? 'No company description provided.') ?></p>
                <p><?= $factory['capacity_per_month'] === null ? 'Capacity not provided' : number_format((int) $factory['capacity_per_month']) . ' units/month' ?>
                    · <?= $factory['moq'] === null ? 'MOQ not provided' : 'MOQ ' . number_format((int) $factory['moq']) ?>
                    · <?= $factory['lead_time_days'] === null ? 'Lead time not provided' : (int) $factory['lead_time_days'] . ' days' ?></p>
                <p><?= (int) $factory['product_count'] ?> listed products<?php if ($factory['certifications'] !== null): ?> · <?= e($factory['certifications']) ?><?php endif; ?></p>
                <a class="button button-outline" href="<?= e(app_url('factory/view.php?id=' . (int) $factory['id'])) ?>">View factory</a>
            </article>
        <?php endforeach; ?>
        <?php if ($factories === []): ?><div class="card"><p>No factories match those filters.</p></div><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
