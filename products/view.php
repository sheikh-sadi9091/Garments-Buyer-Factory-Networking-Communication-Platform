<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house', 'factory', 'supplier', 'admin');

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($productId === false || $productId === null || $productId < 1) {
    http_response_code(400);
    exit('A valid product ID is required.');
}
$statement = db()->prepare(
    "SELECT products.*, categories.name AS category_name, companies.id AS company_id,
            companies.name AS company_name, companies.location, companies.verification_status
     FROM products
     INNER JOIN categories ON categories.id = products.category_id
     INNER JOIN companies ON companies.id = products.company_id
     WHERE products.id = :product_id AND products.status = 'active' LIMIT 1"
);
$statement->execute(['product_id' => $productId]);
$product = $statement->fetch();
if ($product === false) {
    http_response_code(404);
    exit('Product not found.');
}

$pageTitle = $product['name'];
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <p class="eyebrow"><?= e($product['category_name']) ?> · <?= e($product['fabric'] ?? 'Fabric not specified') ?></p>
        <h1><?= e($product['name']) ?></h1>
        <?php if ($product['image_path'] !== null): ?><p><img class="product-image" src="<?= e(app_url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>"></p><?php endif; ?>
        <p>From <a href="<?= e(app_url('factory/view.php?id=' . (int) $product['company_id'])) ?>"><?= e($product['company_name']) ?></a> · <?= e($product['location']) ?></p>
        <p><span class="status-pill status-<?= e($product['verification_status']) ?>"><?= e(ucfirst($product['verification_status'])) ?> company</span></p>
    </section>
    <section class="card">
        <h2>Product specifications</h2>
        <p><?= e($product['description'] ?? 'No product description provided.') ?></p>
        <p>Minimum order quantity: <?= number_format((int) $product['moq']) ?></p>
        <p>Indicative price: <?= e(money_format_value($product['price_min'], $product['currency'])) ?>–<?= e(money_format_value($product['price_max'], $product['currency'])) ?></p>
        <p>Prices are indicative and should be confirmed with the supplier.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
