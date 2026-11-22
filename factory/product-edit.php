<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$company = current_company();
$productId = filter_var($_GET['id'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($company === null || $productId === false || $productId === null || $productId < 1) {
    http_response_code(400);
    exit('A valid product is required.');
}
$productStatement = db()->prepare(
    'SELECT * FROM products WHERE id = :product_id AND company_id = :company_id LIMIT 1'
);
$productStatement->execute(['product_id' => $productId, 'company_id' => $company['id']]);
$product = $productStatement->fetch();
if ($product === false) {
    http_response_code(404);
    exit('Product not found.');
}
$values = [
    'name' => $product['name'],
    'category_id' => (string) $product['category_id'],
    'fabric' => (string) ($product['fabric'] ?? ''),
    'description' => (string) ($product['description'] ?? ''),
    'moq' => (string) $product['moq'],
    'price_min' => (string) ($product['price_min'] ?? ''),
    'price_max' => (string) ($product['price_max'] ?? ''),
    'currency' => $product['currency'],
];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        foreach ($values as $key => $_) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($values['name'] === '' || strlen($values['name']) > 190) {
            $errors[] = 'Product name is required and must be at most 190 characters.';
        }
        if (!ctype_digit($values['category_id']) || !ctype_digit($values['moq']) || (int) $values['moq'] < 1) {
            $errors[] = 'Choose a category and enter a valid minimum order quantity.';
        }
        if (strlen($values['fabric']) > 120 || strlen($values['description']) > 10000) {
            $errors[] = 'Fabric or description is too long.';
        }
        foreach (['price_min', 'price_max'] as $price) {
            if ($values[$price] !== '' && !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $values[$price])) {
                $errors[] = 'Prices must be positive amounts with up to two decimal places.';
                break;
            }
        }
        if ($values['price_min'] !== '' && $values['price_max'] !== '' && (float) $values['price_min'] > (float) $values['price_max']) {
            $errors[] = 'Minimum price cannot be higher than maximum price.';
        }
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($values['currency']))) {
            $errors[] = 'Enter a valid three-letter currency code.';
        }
        $imagePath = null;
        if ($errors === []) {
            $categoryCheck = db()->prepare('SELECT id FROM categories WHERE id = :id');
            $categoryCheck->execute(['id' => (int) $values['category_id']]);
            if ($categoryCheck->fetchColumn() === false) {
                $errors[] = 'Select an available category.';
            } else {
                try {
                    $imagePath = save_image_upload($_FILES['image'] ?? [], 'products');
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }
        if ($errors === []) {
            $update = db()->prepare(
                'UPDATE products SET category_id = :category_id, name = :name, fabric = :fabric,
                    description = :description, moq = :moq, price_min = :price_min,
                    price_max = :price_max, currency = :currency,
                    image_path = COALESCE(:image_path, image_path)
                 WHERE id = :product_id AND company_id = :company_id'
            );
            $update->execute([
                'category_id' => (int) $values['category_id'],
                'name' => $values['name'],
                'fabric' => $values['fabric'] !== '' ? $values['fabric'] : null,
                'description' => $values['description'] !== '' ? $values['description'] : null,
                'moq' => (int) $values['moq'],
                'price_min' => $values['price_min'] !== '' ? $values['price_min'] : null,
                'price_max' => $values['price_max'] !== '' ? $values['price_max'] : null,
                'currency' => strtoupper($values['currency']),
                'image_path' => $imagePath,
                'product_id' => $productId,
                'company_id' => $company['id'],
            ]);
            set_flash('success', 'Product details updated.');
            redirect_to(app_url('factory/products.php'));
        }
    }
}
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Edit product';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card"><p class="eyebrow">Product catalog</p><h1>Edit product</h1>
        <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(app_url('factory/product-edit.php?id=' . (int) $productId)) ?>" data-validate>
            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $productId ?>">
            <label for="name">Product name</label><input id="name" name="name" maxlength="190" required value="<?= e($values['name']) ?>">
            <label for="category_id">Category</label><select id="category_id" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $values['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
            <label for="fabric">Fabric</label><input id="fabric" name="fabric" maxlength="120" value="<?= e($values['fabric']) ?>">
            <label for="description">Description</label><textarea id="description" name="description" rows="4" maxlength="10000"><?= e($values['description']) ?></textarea>
            <label for="moq">Minimum order quantity</label><input id="moq" name="moq" type="number" min="1" required value="<?= e($values['moq']) ?>">
            <label for="price_min">Minimum unit price</label><input id="price_min" name="price_min" type="number" min="0" step="0.01" value="<?= e($values['price_min']) ?>">
            <label for="price_max">Maximum unit price</label><input id="price_max" name="price_max" type="number" min="0" step="0.01" value="<?= e($values['price_max']) ?>">
            <label for="currency">Currency code</label><input id="currency" name="currency" maxlength="3" required pattern="[A-Za-z]{3}" value="<?= e($values['currency']) ?>">
            <label for="image">Replace image (JPEG, PNG, or WebP; maximum 5 MB)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <button class="button button-full" type="submit">Save product</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
