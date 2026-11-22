<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('factory', 'supplier');

$company = current_company();
if ($company === null) {
    http_response_code(404);
    exit('Company profile was not found.');
}
$statement = db()->prepare(
    'SELECT orders.id, orders.purchase_order_number, orders.status, orders.quantity,
            orders.total_amount, orders.currency, orders.delivery_date, orders.created_at,
            requirements.product_name, users.name AS buyer_name
     FROM orders
     INNER JOIN quotations ON quotations.id = orders.quotation_id
     INNER JOIN rfq_recipients ON rfq_recipients.id = quotations.rfq_recipient_id
     INNER JOIN rfqs ON rfqs.id = rfq_recipients.rfq_id
     INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     INNER JOIN users ON users.id = orders.buyer_id
     WHERE orders.factory_company_id = :company_id
     ORDER BY orders.created_at DESC'
);
$statement->execute(['company_id' => $company['id']]);
$orders = $statement->fetchAll();

$pageTitle = 'Factory orders';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Factory workspace</p><h1>Orders</h1><p>Manage purchase orders and move production through the shipment timeline.</p></section>
    <section class="card"><div class="table-wrap"><table class="admin-table"><thead><tr><th>PO</th><th>Product</th><th>Buyer</th><th>Quantity</th><th>Total</th><th>Status</th><th>Delivery</th><th>Tracking</th></tr></thead><tbody>
        <?php foreach ($orders as $order): ?><tr>
            <td><?= e($order['purchase_order_number'] ?? '—') ?></td><td><?= e($order['product_name']) ?></td><td><?= e($order['buyer_name']) ?></td>
            <td><?= number_format((int) $order['quantity']) ?></td><td><?= e(number_format((float) $order['total_amount'], 2) . ' ' . $order['currency']) ?></td>
            <td><?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?></td><td><?= e($order['delivery_date'] ?? '—') ?></td>
            <td><a href="<?= e(app_url('orders/view.php?id=' . (int) $order['id'])) ?>">Update / track</a></td>
        </tr><?php endforeach; ?>
        <?php if ($orders === []): ?><tr><td colspan="8">No orders are assigned to your company.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
