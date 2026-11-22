<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if ($user !== null) {
    redirect_to(app_url('dashboard.php'));
}

require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <section class="hero">
        <p class="eyebrow">Bangladesh apparel sourcing network</p>
        <h1>Build trusted connections across the garment supply chain.</h1>
        <p>Buyers share requirements with buying houses. Buying houses coordinate trusted garment factories and suppliers to deliver the right offers.</p>
        <div class="hero-actions">
            <a class="button" href="<?= e(app_url('auth/register.php')) ?>">Create an account</a>
            <a class="button button-outline" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
        </div>
    </section>
    <section class="feature-grid" aria-label="Platform benefits">
        <article class="card"><h2>For buyers</h2><p>Connect with buying houses, share requirements, and compare offers coordinated through their factory networks.</p></article>
        <article class="card"><h2>For buying houses</h2><p>Build a trusted factory network, receive buyer RFQs, and forward requests to suitable manufacturing partners.</p></article>
        <article class="card"><h2>For factories</h2><p>Connect with buying houses, receive relevant RFQs, submit quotations, and manage production orders.</p></article>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
