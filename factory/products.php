<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$user = current_user();
$company = current_company();
if ($user === null || $company === null) {
    http_response_code(404);
    exit('Company profile was not found.');
}
$errors = [];
$values = ['name' => '', 'category_id' => '', 'fabric' => '', 'description' => '', 'moq' => '', 'price_min' => '', 'price_max' => '', 'currency' => 'USD'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_product') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        exit('Your form session expired. Reload the page and try again.');
    }
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($_POST['status'] ?? '');
    if ($productId === false || $productId === null || $productId < 1
        || !in_array($status, ['active', 'inactive'], true)) {
        http_response_code(400);
        exit('Invalid product update.');
    }
    $update = db()->prepare(
        'UPDATE products SET status = :status
         WHERE id = :product_id AND company_id = :company_id'
    );
    $update->execute(['status' => $status, 'product_id' => $productId, 'company_id' => $company['id']]);
    if ($update->rowCount() !== 1) {
        http_response_code(404);
        exit('Product not found or status was not changed.');
    }
    set_flash('success', 'Product listing updated.');
    redirect_to(app_url('factory/products.php'));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            try {
                $imagePath = save_image_upload($_FILES['image'] ?? [], 'products');
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors === []) {
            $categoryCheck = db()->prepare('SELECT id FROM categories WHERE id = :id');
            $categoryCheck->execute(['id' => (int) $values['category_id']]);
            if ($categoryCheck->fetchColumn() === false) {
                $errors[] = 'Select an available category.';
            } else {
                $insert = db()->prepare(
                    'INSERT INTO products
                        (company_id, category_id, name, fabric, description, moq, price_min, price_max, currency, image_path)
                     VALUES
                        (:company_id, :category_id, :name, :fabric, :description, :moq, :price_min, :price_max, :currency, :image_path)'
                );
                $insert->execute([
                    'company_id' => $company['id'],
                    'category_id' => (int) $values['category_id'],
                    'name' => $values['name'],
                    'fabric' => $values['fabric'] !== '' ? $values['fabric'] : null,
                    'description' => $values['description'] !== '' ? $values['description'] : null,
                    'moq' => (int) $values['moq'],
                    'price_min' => $values['price_min'] !== '' ? $values['price_min'] : null,
                    'price_max' => $values['price_max'] !== '' ? $values['price_max'] : null,
                    'currency' => strtoupper($values['currency']),
                    'image_path' => $imagePath,
                ]);
                set_flash('success', 'Product added to your company catalog.');
                redirect_to(app_url('factory/products.php'));
            }
        }
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$productsStatement = db()->prepare(
    'SELECT products.id, products.name, products.moq, products.status, categories.name AS category_name
     FROM products INNER JOIN categories ON categories.id = products.category_id
     WHERE products.company_id = :company_id ORDER BY products.created_at DESC'
);
$productsStatement->execute(['company_id' => $company['id']]);
$products = $productsStatement->fetchAll();

$pageTitle = 'Manage products';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Factory workspace</p><h1>Manage products</h1><p>Add products to your public sourcing catalog.</p></section>
    <section class="form-card narrow">
        <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(app_url('factory/products.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <label for="name">Product name</label><input id="name" name="name" maxlength="190" required value="<?= e($values['name']) ?>">
            <label for="category_id">Category</label><select id="category_id" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $values['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
            <label for="fabric">Fabric</label><input id="fabric" name="fabric" maxlength="120" value="<?= e($values['fabric']) ?>">
            <label for="description">Description</label><textarea id="description" name="description" rows="4" maxlength="10000"><?= e($values['description']) ?></textarea>
            <label for="image">Product image (JPEG, PNG, or WebP; maximum 5 MB)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <label for="moq">Minimum order quantity</label><input id="moq" name="moq" type="number" min="1" required value="<?= e($values['moq']) ?>">
            <label for="price_min">Minimum unit price</label><input id="price_min" name="price_min" type="number" min="0" step="0.01" value="<?= e($values['price_min']) ?>">
            <label for="price_max">Maximum unit price</label><input id="price_max" name="price_max" type="number" min="0" step="0.01" value="<?= e($values['price_max']) ?>">
            <label for="currency">Currency code</label><input id="currency" name="currency" maxlength="3" required pattern="[A-Za-z]{3}" value="<?= e($values['currency']) ?>">
            <button class="button button-full" type="submit">Add product</button>
        </form>
    </section>
    <section class="card admin-section"><h2>Your products</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>Category</th><th>MOQ</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($products as $product): ?><tr><td><a href="<?= e(app_url('products/view.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a><br><a href="<?= e(app_url('factory/product-edit.php?id=' . (int) $product['id'])) ?>">Edit</a></td><td><?= e($product['category_name']) ?></td><td><?= number_format((int) $product['moq']) ?></td><td><?= e(ucfirst($product['status'])) ?>
            <form method="post" action="<?= e(app_url('factory/products.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button class="link-button" type="submit" name="status" value="<?= $product['status'] === 'active' ? 'inactive' : 'active' ?>"><?= $product['status'] === 'active' ? 'Hide listing' : 'Publish listing' ?></button>
            </form>
        </td></tr><?php endforeach; ?>
        <?php if ($products === []): ?><tr><td colspan="4">No products yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
