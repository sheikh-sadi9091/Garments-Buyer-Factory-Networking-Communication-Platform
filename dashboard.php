<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';
require_login();
$user = current_user();

if ($user !== null && $user['role'] === 'admin') {
    redirect_to(app_url('admin/index.php'));
}

$pdo = db();
$company = current_company();
$summary = [];
$links = [];
if ($user['role'] === 'buyer') {
    $count = $pdo->prepare('SELECT COUNT(*) FROM requirements WHERE buyer_id = :user_id');
    $count->execute(['user_id' => $user['id']]);
    $summary['Your requirements'] = (int) $count->fetchColumn();
    $count = $pdo->prepare('SELECT COUNT(*) FROM rfqs WHERE buyer_id = :user_id');
    $count->execute(['user_id' => $user['id']]);
    $summary['RFQs sent'] = (int) $count->fetchColumn();
    $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE buyer_id = :user_id AND status NOT IN (\'completed\', \'cancelled\')');
    $count->execute(['user_id' => $user['id']]);
    $summary['Active orders'] = (int) $count->fetchColumn();
    $links = [
        ['Post requirement', 'requirements/create.php'],
        ['Find buying houses', 'buying_houses/index.php'],
        ['Compare quotations', 'buyer/rfqs.php'],
        ['My orders', 'buyer/orders.php'],
    ];
} elseif ($user['role'] === 'buying_house' && $company !== null) {
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM rfq_recipients WHERE recipient_company_id = :company_id'
    );
    $count->execute(['company_id' => $company['id']]);
    $summary['Buyer RFQs received'] = (int) $count->fetchColumn();
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM buying_house_factories WHERE buying_house_company_id = :company_id'
    );
    $count->execute(['company_id' => $company['id']]);
    $summary['Connected factories'] = (int) $count->fetchColumn();
    $count = $pdo->prepare(
        'SELECT COUNT(DISTINCT rfq_id) FROM rfq_factory_forwardings
         WHERE buying_house_company_id = :house_company_id'
    );
    $count->execute(['house_company_id' => $company['id']]);
    $summary['RFQs forwarded'] = (int) $count->fetchColumn();
    $links = [
        ['Manage factory network', 'buying_house/network.php'],
        ['Review buyer RFQs', 'buying_house/rfqs.php'],
        ['Find factories', 'factory/index.php'],
        ['Browse factory products', 'products/index.php'],
    ];
} elseif (in_array($user['role'], ['factory', 'supplier'], true) && $company !== null) {
    $count = $pdo->prepare('SELECT COUNT(*) FROM products WHERE company_id = :company_id AND status = \'active\'');
    $count->execute(['company_id' => $company['id']]);
    $summary['Active products'] = (int) $count->fetchColumn();
    $count = $pdo->prepare('SELECT COUNT(*) FROM rfq_recipients WHERE recipient_company_id = :company_id AND status IN (\'invited\', \'viewed\')');
    $count->execute(['company_id' => $company['id']]);
    $summary['New RFQ invitations'] = (int) $count->fetchColumn();
    $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE factory_company_id = :company_id AND status NOT IN (\'completed\', \'cancelled\')');
    $count->execute(['company_id' => $company['id']]);
    $summary['Active orders'] = (int) $count->fetchColumn();
    $links = [
        ['Edit company profile', 'companies/profile.php'],
        ['Manage products', 'factory/products.php'],
        ['Received RFQs', 'factory/rfqs.php'],
        ['Manage orders', 'factory/orders.php'],
        ['View messages', 'messages/index.php'],
    ];
}

require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow">Your workspace</p>
        <h1>Welcome, <?= e($user['name']) ?></h1>
        <p>You are signed in as a <?= e(str_replace('_', ' ', $user['role'])) ?>.</p>
    </section>
    <?php if ($summary !== []): ?>
    <section class="admin-section"><div class="admin-stats">
        <?php foreach ($summary as $label => $value): ?><article class="stat-card"><strong><?= $value ?></strong><span><?= e($label) ?></span></article><?php endforeach; ?>
    </div></section>
    <?php endif; ?>
    <?php if ($company !== null): ?>
    <section class="card admin-section">
        <h2><?= e($company['name']) ?></h2>
        <p><?= e($company['location']) ?> · <span class="status-pill status-<?= e($company['verification_status']) ?>"><?= e(ucfirst($company['verification_status'])) ?></span></p>
        <p><a href="<?= e(app_url('companies/profile.php')) ?>">Manage company profile</a></p>
    </section>
    <?php endif; ?>
    <section class="feature-grid">
        <?php foreach ($links as $link): ?>
            <article class="card"><h2><a href="<?= e(app_url($link[1])) ?>"><?= e($link[0]) ?></a></h2></article>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <h2>Your account</h2>
        <p>You are signed in as a <?= e(str_replace('_', ' ', $user['role'])) ?>. Use the shortcuts above to manage sourcing activity.</p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
