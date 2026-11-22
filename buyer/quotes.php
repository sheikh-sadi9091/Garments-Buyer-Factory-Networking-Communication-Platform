<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$rfqId = filter_var($_GET['rfq_id'] ?? null, FILTER_VALIDATE_INT);
if ($rfqId === false || $rfqId === null || $rfqId < 1) {
    http_response_code(400);
    exit('A valid RFQ ID is required.');
}
$pdo = db();
$rfqStatement = $pdo->prepare(
    'SELECT rfqs.id, rfqs.reference_code, rfqs.status, requirements.product_name, requirements.quantity
     FROM rfqs INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     WHERE rfqs.id = :rfq_id AND rfqs.buyer_id = :buyer_id LIMIT 1'
);
$rfqStatement->execute(['rfq_id' => $rfqId, 'buyer_id' => current_user()['id']]);
$rfq = $rfqStatement->fetch();
if ($rfq === false) {
    http_response_code(404);
    exit('RFQ not found.');
}
$quoteStatement = $pdo->prepare(
    'SELECT quotations.id, quotations.unit_price, quotations.currency, quotations.moq,
            quotations.lead_time_days, quotations.terms, quotations.status, quotations.valid_until,
            companies.id AS company_id, companies.owner_user_id AS company_user_id,
            companies.name AS company_name, companies.location,
            companies.verification_status
     FROM quotations
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN companies ON companies.id = quotations.factory_company_id
     WHERE rfq_recipients.rfq_id = :rfq_id
     ORDER BY quotations.unit_price ASC, quotations.lead_time_days ASC'
);
$quoteStatement->execute(['rfq_id' => $rfqId]);
$quotes = $quoteStatement->fetchAll();

$pageTitle = 'Compare quotations';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow"><?= e($rfq['reference_code']) ?></p><h1>Compare quotations</h1><p><?= e($rfq['product_name']) ?> · <?= number_format((int) $rfq['quantity']) ?> units</p></section>
    <?php if ($quotes === []): ?><section class="card"><p>No factory quotations have arrived yet.</p></section>
    <?php else: ?><section class="card"><div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Factory</th><?php foreach ($quotes as $quote): ?><th><?= e($quote['company_name']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <tr><th>Location</th><?php foreach ($quotes as $quote): ?><td><?= e($quote['location']) ?></td><?php endforeach; ?></tr>
            <tr><th>Verification</th><?php foreach ($quotes as $quote): ?><td><span class="status-pill status-<?= e($quote['verification_status']) ?>"><?= e(ucfirst($quote['verification_status'])) ?></span></td><?php endforeach; ?></tr>
            <tr><th>Unit price</th><?php foreach ($quotes as $quote): ?><td><strong><?= e(number_format((float) $quote['unit_price'], 2) . ' ' . $quote['currency']) ?></strong></td><?php endforeach; ?></tr>
            <tr><th>MOQ</th><?php foreach ($quotes as $quote): ?><td><?= number_format((int) $quote['moq']) ?></td><?php endforeach; ?></tr>
            <tr><th>Lead time</th><?php foreach ($quotes as $quote): ?><td><?= (int) $quote['lead_time_days'] ?> days</td><?php endforeach; ?></tr>
            <tr><th>Valid until</th><?php foreach ($quotes as $quote): ?><td><?= e($quote['valid_until'] ?? 'Not specified') ?></td><?php endforeach; ?></tr>
            <tr><th>Terms</th><?php foreach ($quotes as $quote): ?><td><?= nl2br(e($quote['terms'] ?? '—')) ?></td><?php endforeach; ?></tr>
            <tr><th>Actions</th><?php foreach ($quotes as $quote): ?><td><a href="<?= e(app_url('messages/index.php?with=' . (int) $quote['company_user_id'])) ?>">Contact factory</a><br><a href="<?= e(app_url('buyer/orders.php?quote_id=' . (int) $quote['id'])) ?>">Create sample / order</a></td><?php endforeach; ?></tr>
        </tbody>
    </table></div></section><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
