<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$articles = db()->query(
    "SELECT id, title, slug, summary, content, published_at
     FROM news_articles WHERE status = 'published' AND published_at <= CURRENT_TIMESTAMP
     ORDER BY published_at DESC LIMIT 50"
)->fetchAll();

$pageTitle = 'News';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Platform updates</p><h1>News</h1><p>Updates and information for the apparel sourcing community.</p></section>
    <section class="feature-grid">
        <?php foreach ($articles as $article): ?><article class="card">
            <p class="eyebrow"><?= e($article['published_at']) ?></p>
            <h2><a href="<?= e(app_url('news/view.php?slug=' . rawurlencode($article['slug']))) ?>"><?= e($article['title']) ?></a></h2>
            <p><?= e($article['summary'] ?? '') ?></p>
            <a href="<?= e(app_url('news/view.php?slug=' . rawurlencode($article['slug']))) ?>">Read article</a>
        </article><?php endforeach; ?>
        <?php if ($articles === []): ?><article class="card"><p>No published articles yet.</p></article><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
