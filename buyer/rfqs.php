<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$statement = db()->prepare(
    "SELECT rfqs.id, rfqs.reference_code, rfqs.status, rfqs.created_at, requirements.product_name,
            requirements.quantity,
            (SELECT COUNT(*) FROM rfq_recipients rr
             INNER JOIN companies c ON c.id = rr.recipient_company_id
             INNER JOIN users u ON u.id = c.owner_user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE rr.rfq_id = rfqs.id AND r.slug = 'buying_house') AS buying_house_count,
            (SELECT COUNT(*) FROM quotations q INNER JOIN rfq_recipients rr2 ON rr2.id = q.rfq_recipient_id WHERE rr2.rfq_id = rfqs.id) AS quote_count,
            (SELECT COUNT(*) FROM rfq_recipients rr3
             INNER JOIN companies c3 ON c3.id = rr3.recipient_company_id
             INNER JOIN users u3 ON u3.id = c3.owner_user_id
             INNER JOIN roles r3 ON r3.id = u3.role_id
             WHERE rr3.rfq_id = rfqs.id AND r3.slug IN ('factory', 'supplier')) AS factory_count
     FROM rfqs INNER JOIN requirements ON requirements.id = rfqs.requirement_id
     WHERE rfqs.buyer_id = :buyer_id ORDER BY rfqs.created_at DESC"
);
$statement->execute(['buyer_id' => current_user()['id']]);
$rfqs = $statement->fetchAll();

$pageTitle = 'My RFQs';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Buyer workspace</p><h1>My RFQs</h1><p>Buying houses receive your request, coordinate factory responses, and bring quotations back to you.</p><a class="button button-outline" href="<?= e(app_url('requirements/index.php')) ?>">Browse requirements</a></section>
    <section class="card"><div class="table-wrap"><table class="admin-table"><thead><tr><th>Reference</th><th>Product</th><th>Quantity</th><th>Buying houses</th><th>Factories reached</th><th>Quotes</th><th>Status</th><th>Sent</th></tr></thead><tbody>
        <?php foreach ($rfqs as $rfq): ?><tr>
            <td><?= e($rfq['reference_code']) ?></td>
            <td><?= e($rfq['product_name']) ?></td>
            <td><?= number_format((int) $rfq['quantity']) ?></td>
            <td><?= (int) $rfq['buying_house_count'] ?></td>
            <td><?= (int) $rfq['factory_count'] ?></td>
            <td><a href="<?= e(app_url('buyer/quotes.php?rfq_id=' . (int) $rfq['id'])) ?>"><?= (int) $rfq['quote_count'] ?> · compare</a></td>
            <td><span class="status-pill"><?= e(ucfirst($rfq['status'])) ?></span></td>
            <td><?= e($rfq['created_at']) ?></td>
        </tr><?php endforeach; ?>
        <?php if ($rfqs === []): ?><tr><td colspan="8">You have not sent any RFQs yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
