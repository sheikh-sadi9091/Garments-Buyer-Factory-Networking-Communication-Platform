<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer');

$user = current_user();
$requirementId = filter_var($_GET['requirement_id'] ?? $_POST['requirement_id'] ?? null, FILTER_VALIDATE_INT);
if ($requirementId === false || $requirementId === null || $requirementId < 1) {
    http_response_code(400);
    exit('A valid requirement ID is required.');
}
$requirementStatement = db()->prepare(
    "SELECT id, buyer_id, product_name, quantity, status FROM requirements
     WHERE id = :id AND buyer_id = :buyer_id LIMIT 1"
);
$requirementStatement->execute(['id' => $requirementId, 'buyer_id' => $user['id']]);
$requirement = $requirementStatement->fetch();
if ($requirement === false || $requirement['status'] !== 'open') {
    http_response_code(404);
    exit('An open requirement owned by you is required to send an RFQ.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $buyingHouseIds = $_POST['buying_house_ids'] ?? [];
        if (!is_array($buyingHouseIds)) {
            $buyingHouseIds = [];
        }
        $message = trim((string) ($_POST['message'] ?? ''));
        $deadline = trim((string) ($_POST['response_deadline'] ?? ''));
        if (!is_array($buyingHouseIds) || $buyingHouseIds === [] || count($buyingHouseIds) > 50) {
            $errors[] = 'Select between 1 and 50 buying houses.';
        }
        if (strlen($message) > 10000) {
            $errors[] = 'The RFQ message is too long.';
        }
        if ($deadline !== '' && !date_input_is_valid($deadline)) {
            $errors[] = 'Enter a valid response deadline.';
        }
        $validIds = [];
        if (is_array($buyingHouseIds)) {
            foreach ($buyingHouseIds as $buyingHouseId) {
                $id = filter_var($buyingHouseId, FILTER_VALIDATE_INT);
                if ($id === false || $id === null || $id < 1) {
                    $errors[] = 'The selected buying-house list is invalid.';
                    break;
                }
                $validIds[] = (int) $id;
            }
        }
        $validIds = array_values(array_unique($validIds));
        if (count($validIds) !== count($buyingHouseIds)) {
            $errors[] = 'Select each buying house only once.';
        }

        if ($errors === []) {
            $placeholders = [];
            $parameters = [];
            foreach ($validIds as $index => $id) {
                $key = 'company_' . $index;
                $placeholders[] = ':' . $key;
                $parameters[$key] = $id;
            }
            $buyingHouseQuery = db()->prepare(
                'SELECT companies.id, companies.owner_user_id
                 FROM companies
                 INNER JOIN users ON users.id = companies.owner_user_id
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE roles.slug = \'buying_house\' AND companies.id IN (' . implode(', ', $placeholders) . ')'
            );
            $buyingHouseQuery->execute($parameters);
            $buyingHouses = $buyingHouseQuery->fetchAll();
            if (count($buyingHouses) !== count($validIds)) {
                $errors[] = 'One or more selected buying houses are unavailable.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $reference = 'RFQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $insertRfq = $pdo->prepare(
                        'INSERT INTO rfqs (requirement_id, buyer_id, reference_code, message, response_deadline)
                         VALUES (:requirement_id, :buyer_id, :reference_code, :message, :response_deadline)'
                    );
                    $insertRfq->execute([
                        'requirement_id' => $requirementId,
                        'buyer_id' => $user['id'],
                        'reference_code' => $reference,
                        'message' => $message !== '' ? $message : null,
                        'response_deadline' => $deadline !== '' ? $deadline : null,
                    ]);
                    $rfqId = (int) $pdo->lastInsertId();
                    $insertRecipient = $pdo->prepare(
                        'INSERT INTO rfq_recipients (rfq_id, recipient_company_id) VALUES (:rfq_id, :company_id)'
                    );
                    $insertNotification = $pdo->prepare(
                        'INSERT INTO notifications (user_id, type, title, body, target_url)
                         VALUES (:user_id, :type, :title, :body, :target_url)'
                    );
                    foreach ($buyingHouses as $buyingHouse) {
                        $insertRecipient->execute(['rfq_id' => $rfqId, 'company_id' => $buyingHouse['id']]);
                        $insertNotification->execute([
                            'user_id' => $buyingHouse['owner_user_id'],
                            'type' => 'new_rfq',
                            'title' => 'New request for quotation',
                            'body' => $user['name'] . ' sent your buying house a sourcing request for ' . $requirement['product_name'] . '.',
                            'target_url' => '/buying_house/rfqs.php',
                        ]);
                    }
                    $pdo->commit();
                    set_flash('success', 'RFQ ' . $reference . ' sent to ' . count($buyingHouses) . ' buying houses.');
                    redirect_to(app_url('buyer/rfqs.php'));
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        }
    }
}

$buyingHouses = db()->query(
    "SELECT companies.id, companies.name, companies.location, companies.verification_status,
            (SELECT COUNT(*) FROM buying_house_factories WHERE buying_house_factories.buying_house_company_id = companies.id) AS factory_count
     FROM companies
     INNER JOIN users ON users.id = companies.owner_user_id
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug = 'buying_house'
     ORDER BY companies.verification_status = 'verified' DESC, companies.name"
)->fetchAll();
$selected = array_map('intval', is_array($_POST['buying_house_ids'] ?? null) ? $_POST['buying_house_ids'] : []);

$pageTitle = 'Send RFQ';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Request for quotation</p><h1><?= e($requirement['product_name']) ?></h1><p>Quantity: <?= number_format((int) $requirement['quantity']) ?></p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e(app_url('rfqs/create.php?requirement_id=' . (int) $requirementId)) ?>">
        <?= csrf_field() ?><input type="hidden" name="requirement_id" value="<?= (int) $requirementId ?>">
        <section class="card admin-section">
            <h2>Select buying houses</h2>
            <p>Each selected buying house will review your request and forward it to suitable factories in its network.</p>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Invite</th><th>Buying house</th><th>Location</th><th>Factory network</th></tr></thead><tbody>
                <?php foreach ($buyingHouses as $buyingHouse): ?>
                    <tr>
                        <td><input aria-label="Select <?= e($buyingHouse['name']) ?>" type="checkbox" name="buying_house_ids[]" value="<?= (int) $buyingHouse['id'] ?>" <?= in_array((int) $buyingHouse['id'], $selected, true) ? 'checked' : '' ?>></td>
                        <td><?= e($buyingHouse['name']) ?> <span class="status-pill status-<?= e($buyingHouse['verification_status']) ?>"><?= e(ucfirst($buyingHouse['verification_status'])) ?></span></td>
                        <td><?= e($buyingHouse['location']) ?></td>
                        <td><?= (int) $buyingHouse['factory_count'] ?> connected factories</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($buyingHouses === []): ?><tr><td colspan="4">No buying houses are available yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <section class="card admin-section">
            <label for="message">Message to buying houses</label><textarea id="message" name="message" rows="4" maxlength="10000"><?= e((string) ($_POST['message'] ?? '')) ?></textarea>
            <label for="response_deadline">Quotation deadline</label><input id="response_deadline" name="response_deadline" type="date" value="<?= e((string) ($_POST['response_deadline'] ?? '')) ?>">
            <button class="button" type="submit">Send RFQ</button>
        </section>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
