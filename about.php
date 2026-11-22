<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About';
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">About the platform</p><h1>Better connections for apparel sourcing</h1>
        <p>Garments Network organizes sourcing through the right partners: buyers work with buying houses, and buying houses coordinate their garment-factory and supplier networks to fulfill those requirements.</p>
    </section>
    <section class="feature-grid">
        <article class="card"><h2>A clear sourcing chain</h2><p>Buyers choose buying houses. Buying houses connect to factories and suppliers, then coordinate RFQs and sourcing responses.</p></article>
        <article class="card"><h2>More transparent sourcing</h2><p>Keep requirements, quotations, samples, messages, and order progress connected to the same sourcing workflow.</p></article>
        <article class="card"><h2>Trust through verification</h2><p>Companies can submit verification details for review by the platform administration team.</p></article>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
