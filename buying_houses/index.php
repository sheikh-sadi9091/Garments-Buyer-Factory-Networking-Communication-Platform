<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$query = trim((string) ($_GET['q'] ?? ''));
$location = trim((string) ($_GET['location'] ?? ''));
$where = ["roles.slug = 'buying_house'"];
$parameters = [];
if ($query !== '') {
    $where[] = '(companies.name LIKE :name OR companies.description LIKE :description)';
    $parameters['name'] = '%' . $query . '%';
    $parameters['description'] = '%' . $query . '%';
}
if ($location !== '') {
    $where[] = 'companies.location LIKE :location';
    $parameters['location'] = '%' . $location . '%';
}
$statement = db()->prepare(
    'SELECT companies.id, companies.name, companies.location, companies.description,
            companies.verification_status,
            (SELECT COUNT(*) FROM buying_house_factories
             WHERE buying_house_factories.buying_house_company_id = companies.id) AS factory_count
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY companies.verification_status = \'verified\' DESC, companies.name
     LIMIT 100'
);
$statement->execute($parameters);
$buyingHouses = $statement->fetchAll();

$pageTitle = 'Find buying houses';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Your sourcing partners</p>
        <h1>Find buying houses</h1>
        <p>Share your garment requirement with buying houses. They coordinate with their factory networks and bring suitable offers back to you.</p>
    </section>
    <section class="card admin-section">
        <form class="filter-form" method="get" action="<?= e(app_url('buying_houses/index.php')) ?>">
            <label>Company or keyword<input name="q" value="<?= e($query) ?>" maxlength="120"></label>
            <label>Location<input name="location" value="<?= e($location) ?>" maxlength="120" placeholder="Dhaka, Chattogram..."></label>
            <button class="button" type="submit">Search buying houses</button>
        </form>
    </section>
    <section class="feature-grid">
        <?php foreach ($buyingHouses as $buyingHouse): ?>
            <article class="card">
                <p><span class="status-pill status-<?= e($buyingHouse['verification_status']) ?>"><?= e(ucfirst($buyingHouse['verification_status'])) ?></span></p>
                <h2><a href="<?= e(app_url('buying_houses/view.php?id=' . (int) $buyingHouse['id'])) ?>"><?= e($buyingHouse['name']) ?></a></h2>
                <p><?= e($buyingHouse['location']) ?></p>
                <p><?= e($buyingHouse['description'] ?? 'No company description provided.') ?></p>
                <p><?= (int) $buyingHouse['factory_count'] ?> factories in its network</p>
                <a class="button button-outline" href="<?= e(app_url('buying_houses/view.php?id=' . (int) $buyingHouse['id'])) ?>">View buying house</a>
            </article>
        <?php endforeach; ?>
        <?php if ($buyingHouses === []): ?><article class="card"><p>No buying houses match those filters.</p></article><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
