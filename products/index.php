<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house', 'factory', 'supplier', 'admin');

$categoryId = trim((string) ($_GET['category'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
$where = ["products.status = 'active'"];
$parameters = [];
if ($categoryId !== '') {
    if (!ctype_digit($categoryId)) {
        http_response_code(400);
        exit('Category must be a whole number.');
    }
    $where[] = 'products.category_id = :category_id';
    $parameters['category_id'] = (int) $categoryId;
}
if ($query !== '') {
    $where[] = '(products.name LIKE :product_name OR products.fabric LIKE :product_fabric OR companies.name LIKE :company_name)';
    $parameters['product_name'] = '%' . $query . '%';
    $parameters['product_fabric'] = '%' . $query . '%';
    $parameters['company_name'] = '%' . $query . '%';
}
$productsStatement = db()->prepare(
    'SELECT products.id, products.name, products.fabric, products.image_path, products.moq, products.price_min, products.price_max,
            products.currency, categories.name AS category_name, companies.name AS company_name, companies.location
     FROM products
     INNER JOIN categories ON categories.id = products.category_id
     INNER JOIN companies ON companies.id = products.company_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY products.created_at DESC LIMIT 100'
);
$productsStatement->execute($parameters);
$products = $productsStatement->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Products';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Apparel catalog</p><h1>Products</h1><p>Explore products from garment factories and suppliers.</p></section>
    <section class="card admin-section">
        <form class="filter-form" method="get" action="<?= e(app_url('products/index.php')) ?>">
            <label>Search products or fabric<input name="q" value="<?= e($query) ?>" maxlength="120"></label>
            <label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $categoryId === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <button class="button" type="submit">Filter products</button>
        </form>
    </section>
    <section class="feature-grid">
        <?php foreach ($products as $product): ?>
            <article class="card"><p class="eyebrow"><?= e($product['category_name']) ?></p>
                <?php if ($product['image_path'] !== null): ?><img class="product-image" src="<?= e(app_url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?>
                <h2><a href="<?= e(app_url('products/view.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a></h2>
                <p><?= e($product['company_name']) ?> · <?= e($product['location']) ?></p>
                <p>Fabric: <?= e($product['fabric'] ?? 'Not specified') ?> · MOQ <?= number_format((int) $product['moq']) ?></p>
                <p><?= e(money_format_value($product['price_min'], $product['currency'])) ?>–<?= e(money_format_value($product['price_max'], $product['currency'])) ?></p>
                <a href="<?= e(app_url('products/view.php?id=' . (int) $product['id'])) ?>">Product details</a>
            </article>
        <?php endforeach; ?>
        <?php if ($products === []): ?><article class="card"><p>No products found.</p></article><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
