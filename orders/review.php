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
$statement = db()->prepare(
    'SELECT orders.id, orders.buyer_id, orders.factory_company_id, orders.status,
            companies.owner_user_id AS factory_owner_id, companies.name AS company_name
     FROM orders INNER JOIN companies ON companies.id = orders.factory_company_id
     WHERE orders.id = :order_id LIMIT 1'
);
$statement->execute(['order_id' => $orderId]);
$order = $statement->fetch();
if ($order === false || $order['status'] !== 'completed') {
    http_response_code(404);
    exit('Only completed orders can be reviewed.');
}
$isBuyer = (int) $order['buyer_id'] === (int) $user['id'];
$isFactory = (int) $order['factory_owner_id'] === (int) $user['id'];
if (!$isBuyer && !$isFactory) {
    http_response_code(403);
    exit('You are not a participant in this order.');
}
$revieweeId = $isBuyer ? (int) $order['factory_owner_id'] : (int) $order['buyer_id'];
$existing = db()->prepare(
    'SELECT id, rating, comment FROM reviews
     WHERE order_id = :order_id AND reviewer_id = :reviewer_id AND reviewee_id = :reviewee_id LIMIT 1'
);
$existing->execute(['order_id' => $orderId, 'reviewer_id' => $user['id'], 'reviewee_id' => $revieweeId]);
$existingReview = $existing->fetch();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } elseif ($existingReview !== false) {
        $errors[] = 'You have already reviewed this completed order.';
    } else {
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
            $errors[] = 'Choose a rating from 1 to 5.';
        }
        if (strlen($comment) > 5000) {
            $errors[] = 'Review comments must be 5,000 characters or fewer.';
        }
        if ($errors === []) {
            $insert = db()->prepare(
                'INSERT INTO reviews (order_id, reviewer_id, reviewee_id, rating, comment)
                 VALUES (:order_id, :reviewer_id, :reviewee_id, :rating, :comment)'
            );
            $insert->execute([
                'order_id' => $orderId,
                'reviewer_id' => $user['id'],
                'reviewee_id' => $revieweeId,
                'rating' => $rating,
                'comment' => $comment !== '' ? $comment : null,
            ]);
            $notice = db()->prepare(
                'INSERT INTO notifications (user_id, type, title, body, target_url)
                 VALUES (:user_id, :type, :title, :body, :target_url)'
            );
            $notice->execute([
                'user_id' => $revieweeId,
                'type' => 'new_review',
                'title' => 'You received a new order review',
                'body' => 'A completed order participant left a review.',
                'target_url' => '/dashboard.php',
            ]);
            set_flash('success', 'Thank you for reviewing your completed order.');
            redirect_to(app_url('orders/view.php?id=' . (int) $orderId));
        }
    }
}

$pageTitle = 'Review order';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card"><p class="eyebrow">Completed order</p><h1>Review <?= e($order['company_name']) ?></h1>
        <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($existingReview !== false): ?><p>You rated this order <?= (int) $existingReview['rating'] ?>/5.</p>
        <?php else: ?><form method="post" action="<?= e(app_url('orders/review.php?id=' . (int) $orderId)) ?>">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
            <label for="rating">Rating</label><select id="rating" name="rating" required><option value="">Select a rating</option><?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>"><?= $rating ?> / 5</option><?php endfor; ?></select>
            <label for="comment">Comment</label><textarea id="comment" name="comment" rows="5" maxlength="5000"></textarea>
            <button class="button button-full" type="submit">Submit review</button>
        </form><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
