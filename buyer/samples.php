<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    http_response_code(400);
    exit('Invalid sample review request.');
}
$sampleId = filter_var($_POST['sample_id'] ?? null, FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
if ($sampleId === false || $sampleId === null || $sampleId < 1 || !in_array($decision, ['received', 'approved', 'rejected'], true)) {
    http_response_code(400);
    exit('Invalid sample update.');
}
$allowedPreviousStatus = $decision === 'received' ? 'sent' : 'received';
$pdo = db();
$pdo->beginTransaction();
try {
    $update = $pdo->prepare(
        'UPDATE samples SET status = :status
         WHERE id = :sample_id AND requested_by = :buyer_id AND status = :previous_status'
    );
    $update->execute([
        'status' => $decision,
        'sample_id' => $sampleId,
        'buyer_id' => current_user()['id'],
        'previous_status' => $allowedPreviousStatus,
    ]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        exit('The sample status changed or the sample does not belong to your account.');
    }
    $notice = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, target_url)
         SELECT companies.owner_user_id, :type, :title, :body, :target_url
         FROM samples
         INNER JOIN quotations ON quotations.id = samples.quotation_id
         INNER JOIN companies ON companies.id = quotations.factory_company_id
         WHERE samples.id = :sample_id'
    );
    $notice->execute([
        'type' => 'sample_update',
        'title' => 'Buyer updated the sample status',
        'body' => 'The buyer marked the sample as ' . $decision . '.',
        'target_url' => '/factory/samples.php',
        'sample_id' => $sampleId,
    ]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

set_flash('success', 'Sample marked ' . $decision . '.');
redirect_to(app_url('buyer/orders.php'));
