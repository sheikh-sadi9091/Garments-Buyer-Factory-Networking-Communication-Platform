<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$company = current_company();
if ($company === null) {
    http_response_code(404);
    exit('Company profile was not found.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }
    $sampleId = filter_var($_POST['sample_id'] ?? null, FILTER_VALIDATE_INT);
    $trackingNumber = trim((string) ($_POST['tracking_number'] ?? ''));
    if ($sampleId === false || $sampleId === null || $sampleId < 1 || strlen($trackingNumber) > 120) {
        http_response_code(400);
        exit('Enter a valid sample and tracking number.');
    }
    $update = db()->prepare(
        'UPDATE samples
         INNER JOIN quotations ON quotations.id = samples.quotation_id
         SET samples.status = \'sent\', samples.tracking_number = :tracking_number
         WHERE samples.id = :sample_id AND quotations.factory_company_id = :company_id
           AND samples.status = \'requested\''
    );
    $update->execute([
        'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
        'sample_id' => $sampleId,
        'company_id' => $company['id'],
    ]);
    if ($update->rowCount() !== 1) {
        http_response_code(409);
        exit('The sample has already been updated or does not belong to your company.');
    }
    $notice = db()->prepare(
        'INSERT INTO notifications (user_id, type, title, body, target_url)
         SELECT samples.requested_by, :type, :title, :body, :target_url FROM samples WHERE samples.id = :sample_id'
    );
    $notice->execute([
        'type' => 'sample_update',
        'title' => 'Factory shipped your sample',
        'body' => 'The sample shipment status is now sent.' . ($trackingNumber !== '' ? ' Tracking: ' . $trackingNumber : ''),
        'target_url' => '/buyer/orders.php',
        'sample_id' => $sampleId,
    ]);
    set_flash('success', 'Sample shipment updated.');
    redirect_to(app_url('factory/samples.php'));
}

$statement = db()->prepare(
    'SELECT samples.id, samples.status, samples.tracking_number, samples.notes, samples.requested_at,
            samples.requested_by, users.name AS buyer_name, requirements.product_name
     FROM samples
     INNER JOIN quotations ON quotations.id = samples.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN users ON users.id = samples.requested_by
     WHERE quotations.factory_company_id = :company_id
     ORDER BY samples.requested_at DESC'
);
$statement->execute(['company_id' => $company['id']]);
$samples = $statement->fetchAll();

$pageTitle = 'Sample requests';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Factory workspace</p><h1>Sample requests</h1><p>Ship requested samples and share tracking information.</p></section>
    <section class="card"><div class="table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>Buyer</th><th>Notes</th><th>Status</th><th>Tracking</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($samples as $sample): ?><tr>
            <td><?= e($sample['product_name']) ?></td><td><?= e($sample['buyer_name']) ?></td><td><?= nl2br(e($sample['notes'] ?? '—')) ?></td>
            <td><?= e(ucfirst($sample['status'])) ?></td><td><?= e($sample['tracking_number'] ?? '—') ?></td>
            <td><?php if ($sample['status'] === 'requested'): ?><form method="post" action="<?= e(app_url('factory/samples.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="sample_id" value="<?= (int) $sample['id'] ?>">
                <label for="tracking-<?= (int) $sample['id'] ?>">Tracking number</label><input id="tracking-<?= (int) $sample['id'] ?>" name="tracking_number" maxlength="120">
                <button class="button" type="submit">Mark shipped</button>
            </form><?php else: ?>—<?php endif; ?></td>
        </tr><?php endforeach; ?>
        <?php if ($samples === []): ?><tr><td colspan="6">No sample requests have arrived.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
