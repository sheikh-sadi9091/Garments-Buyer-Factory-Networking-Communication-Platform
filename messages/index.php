<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

$user = current_user();
$currentCompany = $user['role'] === 'buying_house' ? current_company() : null;
if ($user['role'] === 'buying_house' && $currentCompany === null) {
    http_response_code(404);
    exit('Buying-house profile was not found.');
}
$contactsStatement = match ($user['role']) {
    'buyer' => db()->prepare(
        "SELECT DISTINCT peers.id, peers.name, companies.name AS company_name
         FROM rfqs
         INNER JOIN rfq_recipients ON rfq_recipients.rfq_id = rfqs.id
         INNER JOIN companies ON companies.id = rfq_recipients.recipient_company_id
         INNER JOIN users AS peers ON peers.id = companies.owner_user_id
         INNER JOIN roles ON roles.id = peers.role_id
         WHERE rfqs.buyer_id = :user_id AND roles.slug = 'buying_house'
         ORDER BY peers.name"
    ),
    'buying_house' => db()->prepare(
        "SELECT DISTINCT peers.id, peers.name, peers.company_name
         FROM (
             SELECT buyer.id, buyer.name, buyer_company.name AS company_name
             FROM rfqs
             INNER JOIN rfq_recipients ON rfq_recipients.rfq_id = rfqs.id
             INNER JOIN users buyer ON buyer.id = rfqs.buyer_id
             LEFT JOIN companies buyer_company ON buyer_company.owner_user_id = buyer.id
             WHERE rfq_recipients.recipient_company_id = :house_rfq_company
             UNION
             SELECT factory_user.id, factory_user.name, factory.name AS company_name
             FROM buying_house_factories
             INNER JOIN companies house ON house.id = buying_house_factories.buying_house_company_id
             INNER JOIN companies factory ON factory.id = buying_house_factories.factory_company_id
             INNER JOIN users factory_user ON factory_user.id = factory.owner_user_id
             WHERE house.owner_user_id = :house_network_user
         ) peers
         ORDER BY peers.name"
    ),
    default => db()->prepare(
        "SELECT DISTINCT peers.id, peers.name, house.name AS company_name
         FROM buying_house_factories
         INNER JOIN companies factory ON factory.id = buying_house_factories.factory_company_id
         INNER JOIN companies house ON house.id = buying_house_factories.buying_house_company_id
         INNER JOIN users AS peers ON peers.id = house.owner_user_id
         WHERE factory.owner_user_id = :factory_user
         ORDER BY peers.name"
    ),
};
$contactsStatement->execute(match ($user['role']) {
    'buyer' => ['user_id' => $user['id']],
    'buying_house' => ['house_rfq_company' => $currentCompany['id'], 'house_network_user' => $user['id']],
    default => ['factory_user' => $user['id']],
});
$contacts = $contactsStatement->fetchAll();

$partnerId = filter_var($_GET['with'] ?? null, FILTER_VALIDATE_INT);
$partner = null;
if ($partnerId !== false && $partnerId !== null && $partnerId > 0) {
    foreach ($contacts as $contact) {
        if ((int) $contact['id'] === $partnerId) {
            $partner = $contact;
            break;
        }
    }
    if ($partner === null) {
        http_response_code(403);
        exit('You can only message your buying-house or factory network contacts.');
    }
}
if ($partner === null && $contacts !== []) {
    $partner = $contacts[0];
    $partnerId = (int) $partner['id'];
}

$pageTitle = 'Messages';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Sourcing conversations</p><h1>Messages</h1><p>Buyers talk with their buying houses; buying houses coordinate directly with connected factories.</p></section>
    <?php if ($partner === null): ?>
        <section class="card"><p>No RFQ conversations are available yet. Send or receive an RFQ to start a conversation.</p></section>
    <?php else: ?>
        <section class="card chat-card" data-chat data-partner-id="<?= (int) $partnerId ?>" data-current-user-id="<?= (int) $user['id'] ?>" data-api-url="<?= e(app_url('api/messages.php')) ?>" data-csrf-token="<?= e(csrf_token()) ?>" data-download-url="<?= e(app_url('api/message-download.php')) ?>">
            <div class="chat-layout">
                <aside class="chat-contacts" aria-label="Conversations">
                    <?php foreach ($contacts as $contact): ?>
                        <a class="chat-contact <?= (int) $contact['id'] === (int) $partnerId ? 'is-selected' : '' ?>" href="<?= e(app_url('messages/index.php?with=' . (int) $contact['id'])) ?>">
                            <?= e($contact['company_name'] ?? $contact['name']) ?><small><?= e($contact['name']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </aside>
                <div class="chat-main">
                    <h2><?= e($partner['company_name'] ?? $partner['name']) ?></h2>
                    <div class="chat-messages" data-messages aria-live="polite"><p>Loading messages…</p></div>
                    <form class="chat-form" data-message-form>
                        <label class="sr-only" for="chat-message">Message</label>
                        <textarea id="chat-message" name="body" rows="3" maxlength="10000" placeholder="Write a message…" aria-label="Message"></textarea>
                        <label for="chat-attachment">Attach PDF or image (maximum 10 MB)</label>
                        <input id="chat-attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                        <button class="button" type="submit">Send message</button>
                        <p class="form-note" data-chat-status role="status"></p>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
