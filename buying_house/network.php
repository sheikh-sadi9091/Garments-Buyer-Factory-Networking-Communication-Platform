<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house');

$user = current_user();
$company = current_company();
if ($user === null || $company === null) {
    http_response_code(404);
    exit('Buying-house profile was not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $factoryId = filter_var($_POST['factory_company_id'] ?? null, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        if ($factoryId === false || $factoryId === null || $factoryId < 1
            || !in_array($action, ['connect', 'disconnect'], true)) {
            http_response_code(400);
            exit('Select a valid factory and network action.');
        }
        $factoryCheck = db()->prepare(
            "SELECT companies.id FROM companies
             INNER JOIN users ON users.id = companies.owner_user_id
             INNER JOIN roles ON roles.id = users.role_id
             WHERE companies.id = :company_id AND roles.slug IN ('factory', 'supplier')"
        );
        $factoryCheck->execute(['company_id' => $factoryId]);
        if ($factoryCheck->fetchColumn() === false) {
            http_response_code(404);
            exit('Factory not found.');
        }
        if ($action === 'connect') {
            $update = db()->prepare(
                'INSERT IGNORE INTO buying_house_factories (buying_house_company_id, factory_company_id)
                 VALUES (:buying_house_id, :factory_id)'
            );
            $update->execute(['buying_house_id' => $company['id'], 'factory_id' => $factoryId]);
            set_flash('success', $update->rowCount() > 0 ? 'Factory connected to your network.' : 'Factory is already in your network.');
        } else {
            $update = db()->prepare(
                'DELETE FROM buying_house_factories
                 WHERE buying_house_company_id = :buying_house_id AND factory_company_id = :factory_id'
            );
            $update->execute(['buying_house_id' => $company['id'], 'factory_id' => $factoryId]);
            set_flash('success', 'Factory disconnected from your network.');
        }
        redirect_to(app_url('buying_house/network.php'));
    }
}

$factories = db()->prepare(
    "SELECT companies.id, companies.name, companies.location, companies.verification_status,
            buying_house_factories.connected_at
     FROM buying_house_factories
     INNER JOIN companies ON companies.id = buying_house_factories.factory_company_id
     WHERE buying_house_factories.buying_house_company_id = :buying_house_id
     ORDER BY companies.name"
);
$factories->execute(['buying_house_id' => $company['id']]);
$factories = $factories->fetchAll();
$connectedIds = array_map(static fn(array $factory): int => (int) $factory['id'], $factories);
$availableFactories = db()->query(
    "SELECT companies.id, companies.name, companies.location, companies.verification_status
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug IN ('factory', 'supplier')
     ORDER BY companies.verification_status = 'verified' DESC, companies.name"
)->fetchAll();

$pageTitle = 'Factory network';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Buying-house workspace</p><h1>Your factory network</h1><p>Connect trusted factories. You can forward buyer RFQs only to factories in this network.</p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <section class="card admin-section">
        <h2>Connected factories (<?= count($factories) ?>)</h2>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>Factory</th><th>Location</th><th>Verification</th><th>Connected</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($factories as $factory): ?><tr>
                <td><?= e($factory['name']) ?></td><td><?= e($factory['location']) ?></td>
                <td><span class="status-pill status-<?= e($factory['verification_status']) ?>"><?= e(ucfirst($factory['verification_status'])) ?></span></td>
                <td><?= e($factory['connected_at']) ?></td>
                <td><form method="post" action="<?= e(app_url('buying_house/network.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="factory_company_id" value="<?= (int) $factory['id'] ?>">
                    <button class="button button-outline" type="submit" name="action" value="disconnect">Disconnect</button>
                </form></td>
            </tr><?php endforeach; ?>
            <?php if ($factories === []): ?><tr><td colspan="5">No factories connected yet. Add partners below.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="feature-grid">
        <?php foreach ($availableFactories as $factory): ?>
            <article class="card">
                <p><span class="status-pill status-<?= e($factory['verification_status']) ?>"><?= e(ucfirst($factory['verification_status'])) ?></span></p>
                <h2><?= e($factory['name']) ?></h2><p><?= e($factory['location']) ?></p>
                <?php if (in_array((int) $factory['id'], $connectedIds, true)): ?>
                    <p class="eyebrow">Already in your network</p>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('buying_house/network.php')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="factory_company_id" value="<?= (int) $factory['id'] ?>">
                        <button class="button" type="submit" name="action" value="connect">Connect factory</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
