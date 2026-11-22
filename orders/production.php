<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$user = current_user();
$company = current_company();
$orderId = filter_var($_GET['id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId < 1 || $company === null || $user === null) {
    http_response_code(400);
    exit('A valid order is required.');
}
$orderStatement = db()->prepare(
    'SELECT orders.id, orders.buyer_id, orders.status, orders.purchase_order_number,
            requirements.product_name
     FROM orders
     INNER JOIN quotations ON quotations.id = orders.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     WHERE orders.id = :order_id AND orders.factory_company_id = :company_id LIMIT 1'
);
$orderStatement->execute(['order_id' => $orderId, 'company_id' => $company['id']]);
$order = $orderStatement->fetch();
if ($order === false) {
    http_response_code(404);
    exit('Order not found.');
}

$stages = ['cutting', 'sewing', 'finishing', 'qc', 'shipment'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $stage = (string) ($_POST['stage'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!in_array($stage, $stages, true) || !in_array($status, ['in_progress', 'completed', 'issue'], true)) {
            $errors[] = 'Select a valid production stage and update.';
        }
        if (strlen($note) > 2000) {
            $errors[] = 'Production notes must be at most 2,000 characters.';
        }
        if ($stage === 'shipment' && !in_array($order['status'], ['shipment', 'completed'], true)) {
            $errors[] = 'Shipment tracking can only be updated once the order enters the shipment stage.';
        } elseif ($stage !== 'shipment' && $order['status'] !== 'production') {
            $errors[] = 'Production stages can only be updated while the order is in production.';
        }

        $stageIndex = array_search($stage, $stages, true);
        if ($errors === [] && $stageIndex > 0) {
            $previousStage = $stages[$stageIndex - 1];
            $previous = db()->prepare(
                'SELECT status FROM production_updates WHERE order_id = :order_id AND stage = :stage
                 ORDER BY created_at DESC, id DESC LIMIT 1'
            );
            $previous->execute(['order_id' => $orderId, 'stage' => $previousStage]);
            if ($previous->fetchColumn() !== 'completed') {
                $errors[] = 'Complete the previous production stage before updating this stage.';
            }
        }
        if ($errors === []) {
            $currentStatement = db()->prepare(
                'SELECT status FROM production_updates WHERE order_id = :order_id AND stage = :stage
                 ORDER BY created_at DESC, id DESC LIMIT 1'
            );
            $currentStatement->execute(['order_id' => $orderId, 'stage' => $stage]);
            $currentStatus = $currentStatement->fetchColumn();
            $allowed = [
                false => ['in_progress'],
                'pending' => ['in_progress'],
                'in_progress' => ['completed', 'issue'],
                'issue' => ['in_progress'],
                'completed' => [],
            ];
            if (!in_array($status, $allowed[$currentStatus], true)) {
                $errors[] = 'That production-stage status transition is not allowed.';
            }
        }

        if ($errors === []) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO production_updates (order_id, stage, status, note, updated_by)
                     VALUES (:order_id, :stage, :status, :note, :updated_by)'
                );
                $insert->execute([
                    'order_id' => $orderId,
                    'stage' => $stage,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'updated_by' => $user['id'],
                ]);
                $notice = $pdo->prepare(
                    'INSERT INTO notifications (user_id, type, title, body, target_url)
                     VALUES (:user_id, :type, :title, :body, :target_url)'
                );
                $notice->execute([
                    'user_id' => $order['buyer_id'],
                    'type' => 'production_update',
                    'title' => 'Production tracking updated',
                    'body' => $order['purchase_order_number'] . ': ' . ucfirst($stage) . ' is ' . str_replace('_', ' ', $status) . '.',
                    'target_url' => '/orders/view.php?id=' . (int) $orderId,
                ]);
                $pdo->commit();
                set_flash('success', ucfirst($stage) . ' status updated.');
                redirect_to(app_url('orders/production.php?id=' . (int) $orderId));
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$updatesStatement = db()->prepare(
    'SELECT production_updates.stage, production_updates.status, production_updates.note,
            production_updates.created_at, users.name AS updated_by_name
     FROM production_updates
     LEFT JOIN users ON users.id = production_updates.updated_by
     WHERE production_updates.order_id = :order_id
     ORDER BY production_updates.created_at DESC, production_updates.id DESC'
);
$updatesStatement->execute(['order_id' => $orderId]);
$updates = [];
foreach ($updatesStatement->fetchAll() as $update) {
    if (!isset($updates[$update['stage']])) {
        $updates[$update['stage']] = $update;
    }
}

$pageTitle = 'Production tracking';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow"><?= e($order['purchase_order_number']) ?></p><h1>Production tracking</h1><p><?= e($order['product_name']) ?></p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <section class="card admin-section">
        <ol class="order-timeline"><?php foreach ($stages as $stage): $update = $updates[$stage] ?? null; ?>
            <li class="<?= $update !== null && $update['status'] === 'completed' ? 'is-complete' : '' ?>">
                <strong><?= e(ucfirst($stage)) ?></strong>
                <p><?= e($update === null ? 'Not started' : ucfirst(str_replace('_', ' ', $update['status']))) ?></p>
                <?php if ($update !== null): ?><small><?= e($update['created_at']) ?> · <?= e($update['updated_by_name'] ?? 'Team') ?></small><p><?= e($update['note'] ?? '') ?></p><?php endif; ?>
            </li>
        <?php endforeach; ?></ol>
    </section>
    <?php if ($order['status'] === 'production' || $order['status'] === 'shipment'): ?>
        <section class="card admin-section"><h2>Post a production update</h2><form method="post" action="<?= e(app_url('orders/production.php?id=' . (int) $orderId)) ?>">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
            <label for="stage">Stage</label><select id="stage" name="stage" required><?php foreach ($stages as $stage): ?><option value="<?= e($stage) ?>"><?= e(ucfirst($stage)) ?></option><?php endforeach; ?></select>
            <label for="status">Update</label><select id="status" name="status" required><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="issue">Issue</option></select>
            <label for="note">Note</label><textarea id="note" name="note" rows="3" maxlength="2000"></textarea>
            <button class="button" type="submit">Save production update</button>
        </form></section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
