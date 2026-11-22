<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

$user = current_user();
$orderId = filter_var($_GET['id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId < 1) {
    http_response_code(400);
    exit('A valid order ID is required.');
}
$orderStatement = db()->prepare(
    'SELECT orders.*, requirements.product_name, companies.name AS company_name,
            companies.owner_user_id AS factory_owner_id, users.name AS buyer_name,
            samples.id AS sample_id, samples.status AS sample_status
     FROM orders
     INNER JOIN quotations ON quotations.id = orders.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN companies ON companies.id = orders.factory_company_id
     INNER JOIN users ON users.id = orders.buyer_id
     LEFT JOIN samples ON samples.quotation_id = quotations.id AND samples.status = \'approved\'
     WHERE orders.id = :order_id LIMIT 1'
);
$orderStatement->execute(['order_id' => $orderId]);
$order = $orderStatement->fetch();
if ($order === false) {
    http_response_code(404);
    exit('Order not found.');
}
$isBuyer = (int) $order['buyer_id'] === (int) $user['id'];
$isFactory = (int) $order['factory_owner_id'] === (int) $user['id']
    && in_array($user['role'], ['factory', 'supplier'], true);
$isAdmin = $user['role'] === 'admin';
if (!$isBuyer && !$isFactory && !$isAdmin) {
    http_response_code(403);
    exit('You do not have permission to view this order.');
}

$errors = [];
$transitions = [
    'purchase_order' => 'production',
    'production' => 'shipment',
    'shipment' => 'completed',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isFactory) {
        http_response_code(403);
        exit('Only the factory can update production status.');
    }
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $nextStatus = (string) ($_POST['next_status'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!isset($transitions[$order['status']]) || $transitions[$order['status']] !== $nextStatus) {
            http_response_code(409);
            $errors[] = 'Order status must move forward one step at a time.';
        } elseif (strlen($note) > 2000) {
            $errors[] = 'Production notes must be at most 2,000 characters.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare(
                    'UPDATE orders SET status = :next_status
                     WHERE id = :order_id AND factory_company_id = :company_id AND status = :current_status'
                );
                $update->execute([
                    'next_status' => $nextStatus,
                    'order_id' => $orderId,
                    'company_id' => current_company()['id'],
                    'current_status' => $order['status'],
                ]);
                if ($update->rowCount() !== 1) {
                    $pdo->rollBack();
                    http_response_code(409);
                    $errors[] = 'The order status changed. Reload and try again.';
                } else {
                    $history = $pdo->prepare(
                        'INSERT INTO order_status_history (order_id, status, note, changed_by)
                         VALUES (:order_id, :status, :note, :changed_by)'
                    );
                    $history->execute([
                        'order_id' => $orderId,
                        'status' => $nextStatus,
                        'note' => $note !== '' ? $note : null,
                        'changed_by' => $user['id'],
                    ]);
                    $notice = $pdo->prepare(
                        'INSERT INTO notifications (user_id, type, title, body, target_url)
                         VALUES (:user_id, :type, :title, :body, :target_url)'
                    );
                    $notice->execute([
                        'user_id' => $order['buyer_id'],
                        'type' => 'order_update',
                        'title' => 'Order status updated',
                        'body' => $order['purchase_order_number'] . ' moved to ' . str_replace('_', ' ', $nextStatus) . '.',
                        'target_url' => '/orders/view.php?id=' . (int) $orderId,
                    ]);
                    $pdo->commit();
                    set_flash('success', 'Order moved to ' . str_replace('_', ' ', $nextStatus) . '.');
                    redirect_to(app_url('orders/view.php?id=' . (int) $orderId));
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$historyStatement = db()->prepare(
    'SELECT order_status_history.status, order_status_history.note, order_status_history.changed_at,
            users.name AS changed_by_name
     FROM order_status_history
     LEFT JOIN users ON users.id = order_status_history.changed_by
     WHERE order_status_history.order_id = :order_id ORDER BY order_status_history.changed_at, order_status_history.id'
);
$historyStatement->execute(['order_id' => $orderId]);
$history = $historyStatement->fetchAll();
$productionStatement = db()->prepare(
    'SELECT production_updates.stage, production_updates.status, production_updates.note,
            production_updates.created_at, users.name AS updated_by_name
     FROM production_updates
     LEFT JOIN users ON users.id = production_updates.updated_by
     WHERE production_updates.order_id = :order_id
     ORDER BY production_updates.created_at DESC, production_updates.id DESC'
);
$productionStatement->execute(['order_id' => $orderId]);
$productionUpdates = [];
foreach ($productionStatement->fetchAll() as $update) {
    if (!isset($productionUpdates[$update['stage']])) {
        $productionUpdates[$update['stage']] = $update;
    }
}
$sampleStatus = $order['sample_status'];
$timeline = ['sample', 'approval', 'purchase_order', 'production', 'shipment', 'completed'];
$timelineStatus = $order['status'];
$currentIndex = array_search($timelineStatus, $timeline, true);
$currentIndex = $currentIndex === false ? -1 : $currentIndex;
$pageTitle = 'Track order';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow"><?= e($order['purchase_order_number'] ?? 'Purchase order') ?></p><h1><?= e($order['product_name']) ?></h1><p><?= e($order['company_name']) ?> · <?= e($order['buyer_name']) ?></p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <section class="card admin-section">
        <p>Quantity: <?= number_format((int) $order['quantity']) ?> · Total: <?= e(number_format((float) $order['total_amount'], 2) . ' ' . $order['currency']) ?> · Delivery: <?= e($order['delivery_date'] ?? 'Not set') ?></p>
        <h2>Status timeline</h2>
        <ol class="order-timeline">
            <?php foreach ($timeline as $index => $stage): ?>
                <?php
                $complete = $index <= (int) $currentIndex;
                $stageTime = null;
                $stageNote = null;
                foreach ($history as $entry) {
                    if ($entry['status'] === $stage) {
                        $stageTime = $entry['changed_at'];
                        $stageNote = $entry['note'];
                    }
                }
                if ($stage === 'sample' && $sampleStatus !== null) {
                    $stageNote = 'Sample approved.';
                } elseif ($stage === 'approval' && $sampleStatus === 'approved') {
                    $stageNote = 'Buyer approved the sample.';
                }
                ?>
                <li class="<?= $complete ? 'is-complete' : '' ?>">
                    <strong><?= e(ucfirst(str_replace('_', ' ', $stage))) ?></strong>
                    <?php if ($stageTime !== null): ?><small><?= e($stageTime) ?></small><?php endif; ?>
                    <?php if ($stageNote !== null): ?><p><?= e($stageNote) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <section class="card admin-section"><h2>Order updates</h2>
        <?php if ($history === []): ?><p>No status changes recorded.</p>
        <?php else: ?><ul class="notification-list"><?php foreach ($history as $entry): ?><li><strong><?= e(ucfirst(str_replace('_', ' ', $entry['status']))) ?></strong> · <?= e($entry['changed_at']) ?><p><?= e($entry['note'] ?? '') ?></p><small><?= e($entry['changed_by_name'] ?? 'System') ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <section class="card admin-section"><h2>Production tracking</h2>
        <?php if ($productionUpdates === []): ?><p>Detailed production stages have not been posted yet.</p>
        <?php else: ?><ol class="order-timeline"><?php foreach (['cutting', 'sewing', 'finishing', 'qc', 'shipment'] as $stage): $update = $productionUpdates[$stage] ?? null; ?>
            <li class="<?= $update !== null && $update['status'] === 'completed' ? 'is-complete' : '' ?>">
                <strong><?= e(strtoupper($stage === 'qc' ? 'QC' : $stage)) ?></strong>
                <?php if ($update !== null): ?><p><?= e(ucfirst(str_replace('_', ' ', $update['status']))) ?></p><small><?= e($update['created_at']) ?> · <?= e($update['updated_by_name'] ?? 'Team') ?></small><p><?= e($update['note'] ?? '') ?></p><?php else: ?><p>Not started</p><?php endif; ?>
            </li>
        <?php endforeach; ?></ol><?php endif; ?>
        <?php if ($isFactory && in_array($order['status'], ['production', 'shipment'], true)): ?><a class="button button-outline" href="<?= e(app_url('orders/production.php?id=' . (int) $orderId)) ?>">Update production stages</a><?php endif; ?>
    </section>
    <?php if ($isFactory && isset($transitions[$order['status']])): ?>
        <section class="card admin-section"><h2>Advance production</h2>
            <form method="post" action="<?= e(app_url('orders/view.php?id=' . (int) $orderId)) ?>">
                <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                <input type="hidden" name="next_status" value="<?= e($transitions[$order['status']]) ?>">
                <label for="note">Update note</label><textarea id="note" name="note" rows="3" maxlength="2000"></textarea>
                <button class="button" type="submit">Move to <?= e(str_replace('_', ' ', $transitions[$order['status']])) ?></button>
            </form>
        </section>
    <?php endif; ?>
    <?php if ($isBuyer && $order['status'] === 'completed'): ?><p><a class="button" href="<?= e(app_url('orders/review.php?id=' . (int) $orderId)) ?>">Review this order</a></p><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
