<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$company = current_company();
if ($company === null) {
    http_response_code(404);
    exit('Factory profile was not found.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $recipientId = filter_var($_POST['recipient_id'] ?? null, FILTER_VALIDATE_INT);
        $price = trim((string) ($_POST['unit_price'] ?? ''));
        $currency = strtoupper(trim((string) ($_POST['currency'] ?? 'USD')));
        $moq = trim((string) ($_POST['moq'] ?? ''));
        $leadTime = trim((string) ($_POST['lead_time_days'] ?? ''));
        $terms = trim((string) ($_POST['terms'] ?? ''));
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        if ($recipientId === false || $recipientId === null || $recipientId < 1) {
            $errors[] = 'Invalid RFQ recipient.';
        }
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price) || (float) $price <= 0) {
            $errors[] = 'Enter a positive unit price with up to two decimal places.';
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = 'Enter a valid three-letter currency.';
        }
        if (!ctype_digit($moq) || (int) $moq < 1 || (float) $moq > 4294967295) {
            $errors[] = 'Enter a valid minimum order quantity.';
        }
        if (!ctype_digit($leadTime) || (int) $leadTime < 1 || (int) $leadTime > 65535) {
            $errors[] = 'Enter a valid lead time in days.';
        }
        if (strlen($terms) > 10000) {
            $errors[] = 'Terms are too long.';
        }
        if ($validUntil !== '' && !date_input_is_valid($validUntil)) {
            $errors[] = 'Enter a valid quotation expiry date.';
        }

        if ($errors === []) {
            $pdo = db();
            $access = $pdo->prepare(
                "SELECT rfq_recipients.id, rfqs.id AS rfq_id, rfqs.buyer_id, requirements.product_name
                 FROM rfq_recipients
                 INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
                 INNER JOIN requirements ON requirements.id = rfqs.requirement_id
                 WHERE rfq_recipients.id = :recipient_id AND rfq_recipients.recipient_company_id = :company_id
                   AND rfq_recipients.status <> 'declined' LIMIT 1"
            );
            $access->execute(['recipient_id' => $recipientId, 'company_id' => $company['id']]);
            $invitation = $access->fetch();
            if ($invitation === false) {
                http_response_code(404);
                $errors[] = 'That RFQ invitation is not available to your company.';
            } else {
                $pdo->beginTransaction();
                try {
                    $quote = $pdo->prepare(
                        'INSERT INTO quotations
                            (rfq_recipient_id, factory_company_id, unit_price, currency, moq,
                             lead_time_days, terms, valid_until)
                         VALUES
                            (:recipient_id, :company_id, :unit_price, :currency, :moq,
                             :lead_time_days, :terms, :valid_until)
                         ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price), currency = VALUES(currency),
                            moq = VALUES(moq), lead_time_days = VALUES(lead_time_days), terms = VALUES(terms),
                            valid_until = VALUES(valid_until), status = \'submitted\''
                    );
                    $quote->execute([
                        'recipient_id' => $recipientId,
                        'company_id' => $company['id'],
                        'unit_price' => $price,
                        'currency' => $currency,
                        'moq' => (int) $moq,
                        'lead_time_days' => (int) $leadTime,
                        'terms' => $terms !== '' ? $terms : null,
                        'valid_until' => $validUntil !== '' ? $validUntil : null,
                    ]);
                    $pdo->prepare("UPDATE rfq_recipients SET status = 'quoted' WHERE id = :id")
                        ->execute(['id' => $recipientId]);
                    $pdo->prepare("UPDATE rfqs SET status = 'quoting' WHERE id = :id AND status = 'sent'")
                        ->execute(['id' => $invitation['rfq_id']]);
                    $notification = $pdo->prepare(
                        'INSERT INTO notifications (user_id, type, title, body, target_url)
                         SELECT DISTINCT house.owner_user_id, :type, :title, :body, :target_url
                         FROM rfq_factory_forwardings
                         INNER JOIN companies house ON house.id = rfq_factory_forwardings.buying_house_company_id
                         WHERE rfq_factory_forwardings.rfq_id = :rfq_id
                           AND rfq_factory_forwardings.factory_company_id = :factory_company_id'
                    );
                    $notification->execute([
                        'type' => 'new_quote',
                        'title' => 'A factory quotation is ready',
                        'body' => $company['name'] . ' submitted a quotation for ' . $invitation['product_name'] . '. Review it and coordinate the buyer response.',
                        'target_url' => '/buying_house/rfqs.php',
                        'rfq_id' => $invitation['rfq_id'],
                        'factory_company_id' => $company['id'],
                    ]);
                    $pdo->commit();
                    set_flash('success', 'Your quotation has been submitted.');
                    redirect_to(app_url('factory/rfqs.php'));
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        }
    }
}

$statement = db()->prepare(
    'SELECT rfq_recipients.id AS recipient_id, rfq_recipients.status AS recipient_status,
            rfqs.id AS rfq_id, rfqs.reference_code, rfqs.message AS rfq_message,
            rfqs.response_deadline, rfqs.created_at, requirements.product_name,
            requirements.quantity, requirements.fabric, requirements.target_price,
            requirements.currency AS requirement_currency, users.name AS buyer_name,
            (SELECT GROUP_CONCAT(DISTINCT house.name ORDER BY house.name SEPARATOR \', \')
             FROM rfq_factory_forwardings forwarding
             INNER JOIN companies house ON house.id = forwarding.buying_house_company_id
             WHERE forwarding.rfq_id = rfqs.id
               AND forwarding.factory_company_id = :forwarded_factory_id) AS buying_house_names,
            quotations.id AS quotation_id, quotations.unit_price, quotations.currency AS quote_currency,
            quotations.moq AS quote_moq, quotations.lead_time_days, quotations.terms, quotations.valid_until
     FROM rfq_recipients
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN users ON users.id = rfqs.buyer_id
     LEFT JOIN quotations ON quotations.rfq_recipient_id = rfq_recipients.id
     WHERE rfq_recipients.recipient_company_id = :company_id AND rfq_recipients.status <> \'declined\'
     ORDER BY rfqs.created_at DESC'
);
$statement->execute(['company_id' => $company['id'], 'forwarded_factory_id' => $company['id']]);
$rfqs = $statement->fetchAll();

$pageTitle = 'Received RFQs';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Factory workspace</p><h1>Received RFQs</h1><p>Review sourcing requests forwarded by buying houses and submit your quotation for them to coordinate.</p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php foreach ($rfqs as $rfq): ?>
        <section class="card admin-section">
            <p class="eyebrow"><?= e($rfq['reference_code']) ?> · Forwarded by <?= e($rfq['buying_house_names'] ?? $rfq['buyer_name']) ?></p>
            <h2><?= e($rfq['product_name']) ?></h2>
            <p>Quantity <?= number_format((int) $rfq['quantity']) ?> · Fabric <?= e($rfq['fabric'] ?? 'Flexible') ?> · Target <?= e(money_format_value($rfq['target_price'], $rfq['requirement_currency'])) ?></p>
            <p><?= nl2br(e($rfq['rfq_message'] ?? 'No additional buyer message.')) ?></p>
            <p>Reply by <?= e($rfq['response_deadline'] ?? 'No deadline set') ?></p>
            <form method="post" action="<?= e(app_url('factory/rfqs.php')) ?>" data-validate>
                <?= csrf_field() ?><input type="hidden" name="recipient_id" value="<?= (int) $rfq['recipient_id'] ?>">
                <div class="feature-grid">
                    <label>Unit price<input name="unit_price" type="number" min="0.01" step="0.01" required value="<?= e((string) ($rfq['unit_price'] ?? '')) ?>"></label>
                    <label>Currency<input name="currency" maxlength="3" pattern="[A-Za-z]{3}" required value="<?= e((string) ($rfq['quote_currency'] ?? 'USD')) ?>"></label>
                    <label>MOQ<input name="moq" type="number" min="1" required value="<?= e((string) ($rfq['quote_moq'] ?? $company['moq'] ?? '')) ?>"></label>
                    <label>Lead time (days)<input name="lead_time_days" type="number" min="1" max="65535" required value="<?= e((string) ($rfq['lead_time_days'] ?? $company['lead_time_days'] ?? '')) ?>"></label>
                    <label>Valid until<input name="valid_until" type="date" value="<?= e((string) ($rfq['valid_until'] ?? '')) ?>"></label>
                </div>
                <label>Commercial terms<textarea name="terms" rows="3" maxlength="10000"><?= e((string) ($rfq['terms'] ?? '')) ?></textarea></label>
                <button class="button" type="submit"><?= $rfq['quotation_id'] === null ? 'Submit quotation' : 'Update quotation' ?></button>
            </form>
        </section>
    <?php endforeach; ?>
    <?php if ($rfqs === []): ?><section class="card"><p>No RFQ invitations yet.</p></section><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
