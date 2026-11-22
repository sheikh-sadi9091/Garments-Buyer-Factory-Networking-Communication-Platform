<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

$user = current_user();
$errors = [];
$values = ['subject' => '', 'description' => '', 'against_user_id' => '', 'order_id' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        foreach ($values as $key => $_) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($values['subject'] === '' || strlen($values['subject']) > 190) {
            $errors[] = 'Enter a subject of at most 190 characters.';
        }
        if ($values['description'] === '' || strlen($values['description']) > 20000) {
            $errors[] = 'Enter a description of at most 20,000 characters.';
        }
        $againstUserId = null;
        if ($values['against_user_id'] !== '') {
            $againstUserId = filter_var($values['against_user_id'], FILTER_VALIDATE_INT);
            if ($againstUserId === false || $againstUserId === null || (int) $againstUserId === (int) $user['id']) {
                $errors[] = 'Select another valid account.';
            } else {
                $check = db()->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\'');
                $check->execute(['id' => $againstUserId]);
                if ($check->fetchColumn() === false) {
                    $errors[] = 'The selected account is unavailable.';
                }
            }
        }
        $orderId = null;
        if ($values['order_id'] !== '') {
            $orderId = filter_var($values['order_id'], FILTER_VALIDATE_INT);
            $orderCheck = db()->prepare(
                'SELECT orders.id FROM orders
                 INNER JOIN companies ON companies.id = orders.factory_company_id
                 WHERE orders.id = :order_id AND (orders.buyer_id = :buyer_id OR companies.owner_user_id = :owner_id)'
            );
            if ($orderId === false || $orderId === null) {
                $errors[] = 'Select an order that belongs to your account.';
            } else {
                $orderCheck->execute(['order_id' => $orderId, 'buyer_id' => $user['id'], 'owner_id' => $user['id']]);
                if ($orderCheck->fetchColumn() === false) {
                    $errors[] = 'Select an order that belongs to your account.';
                }
            }
        }

        if ($errors === []) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO complaints (reporter_id, against_user_id, order_id, subject, description)
                     VALUES (:reporter_id, :against_user_id, :order_id, :subject, :description)'
                );
                $insert->execute([
                    'reporter_id' => $user['id'],
                    'against_user_id' => $againstUserId,
                    'order_id' => $orderId,
                    'subject' => $values['subject'],
                    'description' => $values['description'],
                ]);
                $admins = $pdo->query(
                    "SELECT users.id FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'admin' AND users.status = 'active'"
                )->fetchAll(PDO::FETCH_COLUMN);
                $notification = $pdo->prepare(
                    'INSERT INTO notifications (user_id, type, title, body, target_url)
                     VALUES (:user_id, :type, :title, :body, :target_url)'
                );
                foreach ($admins as $adminId) {
                    $notification->execute([
                        'user_id' => $adminId,
                        'type' => 'new_complaint',
                        'title' => 'A new complaint was submitted',
                        'body' => $values['subject'],
                        'target_url' => '/admin/index.php',
                    ]);
                }
                $pdo->commit();
                set_flash('success', 'Your complaint was sent to the admin team.');
                redirect_to(app_url('complaints/index.php'));
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Report a complaint';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card"><p class="eyebrow">Help and safety</p><h1>Report a complaint</h1>
        <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('complaints/create.php')) ?>">
            <?= csrf_field() ?>
            <label for="subject">Subject</label><input id="subject" name="subject" maxlength="190" required value="<?= e($values['subject']) ?>">
            <label for="description">Describe the issue</label><textarea id="description" name="description" rows="6" maxlength="20000" required><?= e($values['description']) ?></textarea>
            <label for="against_user_id">Account involved (optional)</label><input id="against_user_id" name="against_user_id" type="number" min="1" value="<?= e($values['against_user_id']) ?>">
            <label for="order_id">Related order ID (optional)</label><input id="order_id" name="order_id" type="number" min="1" value="<?= e($values['order_id']) ?>">
            <button class="button button-full" type="submit">Send complaint</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
