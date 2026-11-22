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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }
    $rfqId = filter_var($_POST['rfq_id'] ?? null, FILTER_VALIDATE_INT);
    $factoryIds = $_POST['factory_ids'] ?? [];
    if ($rfqId === false || $rfqId === null || $rfqId < 1
        || !is_array($factoryIds) || $factoryIds === [] || count($factoryIds) > 50) {
        http_response_code(400);
        exit('Select a valid RFQ and between 1 and 50 factories.');
    }
    $validIds = [];
    foreach ($factoryIds as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            http_response_code(400);
            exit('The selected factory list is invalid.');
        }
        $validIds[] = (int) $id;
    }
    $validIds = array_values(array_unique($validIds));
    if (count($validIds) !== count($factoryIds)) {
        http_response_code(400);
        exit('Select each factory only once.');
    }

    $access = db()->prepare(
        "SELECT rfqs.id, rfqs.reference_code, requirements.product_name
         FROM rfqs
         INNER JOIN requirements ON requirements.id = rfqs.requirement_id
         INNER JOIN rfq_recipients ON rfq_recipients.rfq_id = rfqs.id
         WHERE rfqs.id = :rfq_id AND rfqs.status NOT IN ('closed', 'cancelled')
           AND rfq_recipients.recipient_company_id = :company_id"
    );
    $access->execute(['rfq_id' => $rfqId, 'company_id' => $company['id']]);
    $rfq = $access->fetch();
    if ($rfq === false) {
        http_response_code(404);
        exit('That RFQ is not assigned to your buying house.');
    }

    $placeholders = [];
    $parameters = ['buying_house_id' => $company['id']];
    foreach ($validIds as $index => $id) {
        $key = 'factory_' . $index;
        $placeholders[] = ':' . $key;
        $parameters[$key] = $id;
    }
    $factoryQuery = db()->prepare(
        "SELECT companies.id, companies.owner_user_id
         FROM buying_house_factories
         INNER JOIN companies ON companies.id = buying_house_factories.factory_company_id
         INNER JOIN users ON users.id = companies.owner_user_id
         INNER JOIN roles ON roles.id = users.role_id
         WHERE buying_house_factories.buying_house_company_id = :buying_house_id
           AND roles.slug IN ('factory', 'supplier')
           AND companies.id IN (" . implode(', ', $placeholders) . ')'
    );
    $factoryQuery->execute($parameters);
    $factories = $factoryQuery->fetchAll();
    if (count($factories) !== count($validIds)) {
        http_response_code(400);
        exit('Choose only factories connected to your buying-house network.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $insertRecipient = $pdo->prepare(
            'INSERT IGNORE INTO rfq_recipients (rfq_id, recipient_company_id)
             VALUES (:rfq_id, :company_id)'
        );
        $insertForwarding = $pdo->prepare(
            'INSERT IGNORE INTO rfq_factory_forwardings
                (rfq_id, buying_house_company_id, factory_company_id)
             VALUES (:rfq_id, :buying_house_id, :factory_id)'
        );
        $notify = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, body, target_url)
             VALUES (:user_id, :type, :title, :body, :target_url)'
        );
        $forwarded = 0;
        foreach ($factories as $factory) {
            $insertRecipient->execute(['rfq_id' => $rfqId, 'company_id' => $factory['id']]);
            $insertForwarding->execute([
                'rfq_id' => $rfqId,
                'buying_house_id' => $company['id'],
                'factory_id' => $factory['id'],
            ]);
            if ($insertForwarding->rowCount() > 0) {
                $forwarded++;
                $notify->execute([
                    'user_id' => $factory['owner_user_id'],
                    'type' => 'new_rfq',
                    'title' => 'A buying house forwarded an RFQ',
                    'body' => $company['name'] . ' invited your factory to quote ' . $rfq['product_name'] . '.',
                    'target_url' => '/factory/rfqs.php',
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    set_flash('success', $forwarded > 0
        ? 'RFQ ' . $rfq['reference_code'] . ' forwarded to ' . $forwarded . ' factories.'
        : 'Those factories already received this RFQ.');
    redirect_to(app_url('buying_house/rfqs.php'));
}

$rfqStatement = db()->prepare(
    "SELECT rfqs.id, rfqs.reference_code, rfqs.message, rfqs.response_deadline, rfqs.status, rfqs.created_at,
            requirements.product_name, requirements.quantity, requirements.fabric, requirements.target_price,
            requirements.currency, buyer_companies.name AS buyer_company,
            (SELECT COUNT(*) FROM rfq_factory_forwardings forwarded
             WHERE forwarded.rfq_id = rfqs.id
               AND forwarded.buying_house_company_id = :forwarding_house_id) AS factory_count,
            (SELECT COUNT(*) FROM quotations
             INNER JOIN rfq_recipients quote_recipient ON quote_recipient.id = quotations.rfq_recipient_id
             INNER JOIN companies quote_company ON quote_company.id = quote_recipient.recipient_company_id
             INNER JOIN users quote_user ON quote_user.id = quote_company.owner_user_id
             INNER JOIN roles quote_role ON quote_role.id = quote_user.role_id
             INNER JOIN rfq_factory_forwardings quote_forwarding
                 ON quote_forwarding.rfq_id = rfqs.id
                AND quote_forwarding.factory_company_id = quote_recipient.recipient_company_id
             WHERE quote_recipient.rfq_id = rfqs.id
               AND quote_forwarding.buying_house_company_id = :quote_house_id
               AND quote_role.slug IN ('factory', 'supplier')) AS quote_count
     FROM rfq_recipients
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     LEFT JOIN companies buyer_companies ON buyer_companies.owner_user_id = rfqs.buyer_id
     WHERE rfq_recipients.recipient_company_id = :company_id
     ORDER BY rfqs.created_at DESC"
);
$rfqStatement->execute([
    'company_id' => $company['id'],
    'forwarding_house_id' => $company['id'],
    'quote_house_id' => $company['id'],
]);
$rfqs = $rfqStatement->fetchAll();
$networkStatement = db()->prepare(
    "SELECT companies.id, companies.name, companies.location, companies.verification_status
     FROM buying_house_factories
     INNER JOIN companies ON companies.id = buying_house_factories.factory_company_id
     WHERE buying_house_factories.buying_house_company_id = :company_id
     ORDER BY companies.name"
);
$networkStatement->execute(['company_id' => $company['id']]);
$factories = $networkStatement->fetchAll();

$pageTitle = 'Buyer RFQs';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Buying-house workspace</p><h1>Buyer RFQs</h1><p>Review buyer requests and forward them only to suitable factories in your network.</p><a class="button button-outline" href="<?= e(app_url('buying_house/network.php')) ?>">Manage factory network</a></section>
    <?php foreach ($rfqs as $rfq): ?>
        <section class="card admin-section">
            <p class="eyebrow"><?= e($rfq['reference_code']) ?> · from <?= e($rfq['buyer_company'] ?? 'Buyer') ?></p>
            <h2><?= e($rfq['product_name']) ?></h2>
            <p><?= number_format((int) $rfq['quantity']) ?> units · <?= e($rfq['fabric'] ?? 'Fabric flexible') ?>
                · Target <?= e(money_format_value($rfq['target_price'], $rfq['currency'])) ?></p>
            <p><?= nl2br(e($rfq['message'] ?? 'No extra message.')) ?></p>
            <p><?= (int) $rfq['factory_count'] ?> factories reached · <?= (int) $rfq['quote_count'] ?> quotations received</p>
            <?php if ($rfq['status'] !== 'closed' && $rfq['status'] !== 'cancelled'): ?>
                <form method="post" action="<?= e(app_url('buying_house/rfqs.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="rfq_id" value="<?= (int) $rfq['id'] ?>">
                    <h3>Forward to your connected factories</h3>
                    <?php if ($factories === []): ?>
                        <p>Connect factories before forwarding this request.</p>
                    <?php else: ?>
                        <div class="admin-stats"><?php foreach ($factories as $factory): ?>
                            <label class="card">
                                <input type="checkbox" name="factory_ids[]" value="<?= (int) $factory['id'] ?>">
                                <?= e($factory['name']) ?> · <?= e($factory['location']) ?>
                                <span class="status-pill status-<?= e($factory['verification_status']) ?>"><?= e(ucfirst($factory['verification_status'])) ?></span>
                            </label>
                        <?php endforeach; ?></div>
                        <button class="button" type="submit">Forward RFQ to selected factories</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <?php if ($rfqs === []): ?><section class="card"><p>No buyer RFQs have been sent to your buying house yet.</p></section><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
