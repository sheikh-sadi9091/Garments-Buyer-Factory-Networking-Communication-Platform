<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$errors = [];
$values = [
    'product_name' => '', 'quantity' => '', 'fabric' => '', 'target_price' => '',
    'currency' => 'USD', 'delivery_date' => '', 'certification_needed' => '', 'details' => '',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        foreach ($values as $key => $_) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($values['product_name'] === '' || strlen($values['product_name']) > 190) {
            $errors[] = 'Product name is required and must be at most 190 characters.';
        }
        if (!ctype_digit($values['quantity']) || (int) $values['quantity'] < 1 || (float) $values['quantity'] > 4294967295) {
            $errors[] = 'Enter a valid order quantity.';
        }
        if (strlen($values['fabric']) > 120 || strlen($values['certification_needed']) > 255 || strlen($values['details']) > 10000) {
            $errors[] = 'One or more fields exceed their maximum length.';
        }
        if ($values['target_price'] !== '' && (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $values['target_price']) || (float) $values['target_price'] <= 0)) {
            $errors[] = 'Target price must be a positive amount with up to two decimal places.';
        }
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($values['currency']))) {
            $errors[] = 'Enter a valid three-letter currency code.';
        }
        if ($values['delivery_date'] !== '' && !date_input_is_valid($values['delivery_date'])) {
            $errors[] = 'Enter a valid delivery date.';
        }

        if ($errors === []) {
            $statement = db()->prepare(
                'INSERT INTO requirements
                    (buyer_id, product_name, quantity, fabric, target_price, currency, delivery_date,
                     certification_needed, details, status)
                 VALUES
                    (:buyer_id, :product_name, :quantity, :fabric, :target_price, :currency, :delivery_date,
                     :certification_needed, :details, \'open\')'
            );
            $statement->execute([
                'buyer_id' => current_user()['id'],
                'product_name' => $values['product_name'],
                'quantity' => (int) $values['quantity'],
                'fabric' => $values['fabric'] !== '' ? $values['fabric'] : null,
                'target_price' => $values['target_price'] !== '' ? $values['target_price'] : null,
                'currency' => strtoupper($values['currency']),
                'delivery_date' => $values['delivery_date'] !== '' ? $values['delivery_date'] : null,
                'certification_needed' => $values['certification_needed'] !== '' ? $values['certification_needed'] : null,
                'details' => $values['details'] !== '' ? $values['details'] : null,
            ]);
            $requirementId = (int) db()->lastInsertId();
            set_flash('success', 'Your requirement is now visible to the sourcing network.');
            redirect_to(app_url('requirements/view.php?id=' . $requirementId));
        }
    }
}

$pageTitle = 'Post a requirement';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card">
        <p class="eyebrow">Create sourcing brief</p><h1>Post a requirement</h1>
        <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('requirements/create.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <label for="product_name">Product</label><input id="product_name" name="product_name" maxlength="190" required value="<?= e($values['product_name']) ?>">
            <label for="quantity">Quantity (units)</label><input id="quantity" name="quantity" type="number" min="1" required value="<?= e($values['quantity']) ?>">
            <label for="fabric">Fabric</label><input id="fabric" name="fabric" maxlength="120" value="<?= e($values['fabric']) ?>">
            <label for="target_price">Target unit price</label><input id="target_price" name="target_price" type="number" min="0.01" step="0.01" value="<?= e($values['target_price']) ?>">
            <label for="currency">Currency code</label><input id="currency" name="currency" maxlength="3" pattern="[A-Za-z]{3}" required value="<?= e($values['currency']) ?>">
            <label for="delivery_date">Requested delivery date</label><input id="delivery_date" name="delivery_date" type="date" value="<?= e($values['delivery_date']) ?>">
            <label for="certification_needed">Certification needed</label><input id="certification_needed" name="certification_needed" maxlength="255" value="<?= e($values['certification_needed']) ?>">
            <label for="details">Additional details</label><textarea id="details" name="details" rows="5" maxlength="10000"><?= e($values['details']) ?></textarea>
            <button class="button button-full" type="submit">Publish requirement</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
