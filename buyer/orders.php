<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$user = current_user();
$errors = [];
$selectedQuote = null;
$quoteId = filter_var($_GET['quote_id'] ?? $_POST['quote_id'] ?? null, FILTER_VALIDATE_INT);
if ($quoteId !== false && $quoteId !== null && $quoteId > 0) {
    $quoteStatement = db()->prepare(
        'SELECT quotations.id, quotations.unit_price, quotations.currency, quotations.moq,
                quotations.status AS quote_status, companies.name AS company_name,
                companies.id AS company_id, rfqs.id AS rfq_id, requirements.quantity,
                requirements.product_name, requirements.buyer_id
         FROM quotations
         INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
         INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
         INNER JOIN requirements ON requirements.id = rfqs.requirement_id
         INNER JOIN companies ON companies.id = quotations.factory_company_id
         WHERE quotations.id = :quote_id AND rfqs.buyer_id = :buyer_id LIMIT 1'
    );
    $quoteStatement->execute(['quote_id' => $quoteId, 'buyer_id' => $user['id']]);
    $selectedQuote = $quoteStatement->fetch();
    if ($selectedQuote === false) {
        http_response_code(404);
        exit('Quotation not found.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $quote = $selectedQuote;
        if ($quote === null) {
            http_response_code(400);
            $errors[] = 'A valid quotation is required.';
        } elseif ($action === 'request_sample') {
            $existing = db()->prepare('SELECT id FROM samples WHERE quotation_id = :quote_id ORDER BY id DESC LIMIT 1');
            $existing->execute(['quote_id' => $quote['id']]);
            if ($existing->fetchColumn() !== false) {
                $errors[] = 'A sample has already been requested for this quotation.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $insert = $pdo->prepare(
                        'INSERT INTO samples (quotation_id, requested_by, notes) VALUES (:quotation_id, :requested_by, :notes)'
                    );
                    $insert->execute([
                        'quotation_id' => $quote['id'],
                        'requested_by' => $user['id'],
                        'notes' => trim((string) ($_POST['sample_notes'] ?? '')) ?: null,
                    ]);
                    $notice = $pdo->prepare(
                        'INSERT INTO notifications (user_id, type, title, body, target_url)
                         SELECT companies.owner_user_id, :type, :title, :body, :target_url
                         FROM companies WHERE companies.id = :company_id'
                    );
                    $notice->execute([
                        'type' => 'sample_requested',
                        'title' => 'A buyer requested a sample',
                        'body' => $user['name'] . ' requested a sample for ' . $quote['product_name'] . '.',
                        'target_url' => '/factory/samples.php',
                        'company_id' => $quote['company_id'],
                    ]);
                    $pdo->commit();
                    set_flash('success', 'Sample request sent to ' . $quote['company_name'] . '.');
                    redirect_to(app_url('buyer/orders.php'));
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        } elseif ($action === 'create_order') {
            $sampleId = filter_var($_POST['sample_id'] ?? null, FILTER_VALIDATE_INT);
            if ($sampleId === false || $sampleId === null || $sampleId < 1) {
                $errors[] = 'A valid approved sample is required before placing an order.';
            } else {
                $sampleCheck = db()->prepare(
                    'SELECT id FROM samples WHERE id = :sample_id AND quotation_id = :quote_id
                     AND requested_by = :buyer_id AND status = \'approved\' LIMIT 1'
                );
                $sampleCheck->execute(['sample_id' => $sampleId, 'quote_id' => $quote['id'], 'buyer_id' => $user['id']]);
                if ($sampleCheck->fetchColumn() === false) {
                    $errors[] = 'Only an approved sample can proceed to purchase order.';
                } else {
                    $purchaseOrder = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $pdo = db();
                    $pdo->beginTransaction();
                    try {
                        $insert = $pdo->prepare(
                            'INSERT INTO orders
                                (quotation_id, buyer_id, factory_company_id, purchase_order_number,
                                 quantity, total_amount, currency, status)
                             VALUES
                                (:quotation_id, :buyer_id, :company_id, :purchase_order_number,
                                 :quantity, :total_amount, :currency, \'purchase_order\')'
                        );
                        $insert->execute([
                            'quotation_id' => $quote['id'],
                            'buyer_id' => $user['id'],
                            'company_id' => $quote['company_id'],
                            'purchase_order_number' => $purchaseOrder,
                            'quantity' => $quote['quantity'],
                            'total_amount' => (float) $quote['unit_price'] * (int) $quote['quantity'],
                            'currency' => $quote['currency'],
                        ]);
                        $orderId = (int) $pdo->lastInsertId();
                        $pdo->prepare(
                            'INSERT INTO order_status_history (order_id, status, note, changed_by)
                             VALUES (:order_id, \'purchase_order\', :note, :changed_by)'
                        )->execute([
                            'order_id' => $orderId,
                            'note' => 'Purchase order created after sample approval.',
                            'changed_by' => $user['id'],
                        ]);
                        $notice = $pdo->prepare(
                            'INSERT INTO notifications (user_id, type, title, body, target_url)
                             SELECT companies.owner_user_id, :type, :title, :body, :target_url
                             FROM companies WHERE companies.id = :company_id'
                        );
                        $notice->execute([
                            'type' => 'order_update',
                            'title' => 'New purchase order',
                            'body' => 'Purchase order ' . $purchaseOrder . ' was created.',
                            'target_url' => '/factory/orders.php',
                            'company_id' => $quote['company_id'],
                        ]);
                        $pdo->commit();
                        set_flash('success', 'Purchase order created.');
                        redirect_to(app_url('orders/view.php?id=' . $orderId));
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $exception;
                    }
                }
            }
        } else {
            http_response_code(400);
            $errors[] = 'Select a valid order action.';
        }
    }
}

$samples = db()->prepare(
    'SELECT samples.id, samples.status, samples.tracking_number, samples.notes, samples.requested_at,
            quotations.id AS quotation_id, quotations.unit_price, quotations.currency,
            requirements.product_name, companies.name AS company_name
     FROM samples
     INNER JOIN quotations ON quotations.id = samples.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN companies ON companies.id = quotations.factory_company_id
     WHERE samples.requested_by = :buyer_id ORDER BY samples.requested_at DESC'
);
$samples->execute(['buyer_id' => $user['id']]);
$sampleRows = $samples->fetchAll();
$orders = db()->prepare(
    'SELECT orders.id, orders.purchase_order_number, orders.status, orders.quantity, orders.total_amount,
            orders.currency, orders.created_at, requirements.product_name, companies.name AS company_name
     FROM orders
     INNER JOIN quotations ON quotations.id = orders.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN companies ON companies.id = orders.factory_company_id
     WHERE orders.buyer_id = :buyer_id ORDER BY orders.created_at DESC'
);
$orders->execute(['buyer_id' => $user['id']]);
$orderRows = $orders->fetchAll();

$pageTitle = 'Orders and samples';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Buyer workspace</p><h1>Orders and samples</h1><p>Request samples, approve them, and track production through shipment.</p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($selectedQuote !== null): ?>
        <section class="card admin-section">
            <h2><?= e($selectedQuote['product_name']) ?> · <?= e($selectedQuote['company_name']) ?></h2>
            <p><?= e(number_format((float) $selectedQuote['unit_price'], 2) . ' ' . $selectedQuote['currency']) ?> per unit · order quantity <?= number_format((int) $selectedQuote['quantity']) ?></p>
            <?php $sampleForQuote = null; foreach ($sampleRows as $sampleRow) { if ((int) $sampleRow['quotation_id'] === (int) $selectedQuote['id']) { $sampleForQuote = $sampleRow; break; } } ?>
            <?php if ($sampleForQuote === null): ?>
                <form method="post" action="<?= e(app_url('buyer/orders.php?quote_id=' . (int) $selectedQuote['id'])) ?>">
                    <?= csrf_field() ?><input type="hidden" name="quote_id" value="<?= (int) $selectedQuote['id'] ?>"><input type="hidden" name="action" value="request_sample">
                    <label for="sample_notes">Sample requirements</label><textarea id="sample_notes" name="sample_notes" rows="3" maxlength="5000"></textarea>
                    <button class="button" type="submit">Request sample</button>
                </form>
            <?php else: ?>
                <p>Sample status: <span class="status-pill"><?= e(ucfirst($sampleForQuote['status'])) ?></span></p>
                <?php if ($sampleForQuote['status'] === 'approved'): ?>
                    <form method="post" action="<?= e(app_url('buyer/orders.php?quote_id=' . (int) $selectedQuote['id'])) ?>">
                        <?= csrf_field() ?><input type="hidden" name="quote_id" value="<?= (int) $selectedQuote['id'] ?>"><input type="hidden" name="sample_id" value="<?= (int) $sampleForQuote['id'] ?>"><input type="hidden" name="action" value="create_order">
                        <button class="button" type="submit">Create purchase order</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="card admin-section"><h2>Sample requests</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>Factory</th><th>Status</th><th>Tracking</th><th>Requested</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($sampleRows as $sample): ?><tr><td><?= e($sample['product_name']) ?></td><td><?= e($sample['company_name']) ?></td><td><?= e(ucfirst($sample['status'])) ?></td><td><?= e($sample['tracking_number'] ?? '—') ?></td><td><?= e($sample['requested_at']) ?></td><td>
            <?php if ($sample['status'] === 'sent'): ?>
                <form method="post" action="<?= e(app_url('buyer/samples.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="sample_id" value="<?= (int) $sample['id'] ?>">
                    <button class="button" name="decision" value="received" type="submit">Received</button>
                </form>
            <?php elseif ($sample['status'] === 'received'): ?>
                <form method="post" action="<?= e(app_url('buyer/samples.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="sample_id" value="<?= (int) $sample['id'] ?>">
                    <button class="button" name="decision" value="approved" type="submit">Approve</button>
                    <button class="button button-outline" name="decision" value="rejected" type="submit">Reject</button>
                </form>
            <?php else: ?>—<?php endif; ?>
        </td></tr><?php endforeach; ?>
        <?php if ($sampleRows === []): ?><tr><td colspan="6">No sample requests yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <section class="card admin-section"><h2>Purchase orders</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>PO</th><th>Product</th><th>Factory</th><th>Quantity</th><th>Total</th><th>Status</th><th>Tracking</th></tr></thead><tbody>
        <?php foreach ($orderRows as $order): ?><tr><td><?= e($order['purchase_order_number'] ?? '—') ?></td><td><?= e($order['product_name']) ?></td><td><?= e($order['company_name']) ?></td><td><?= number_format((int) $order['quantity']) ?></td><td><?= e(number_format((float) $order['total_amount'], 2) . ' ' . $order['currency']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?></td><td><a href="<?= e(app_url('orders/view.php?id=' . (int) $order['id'])) ?>">Track order</a></td></tr><?php endforeach; ?>
        <?php if ($orderRows === []): ?><tr><td colspan="7">No purchase orders yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
