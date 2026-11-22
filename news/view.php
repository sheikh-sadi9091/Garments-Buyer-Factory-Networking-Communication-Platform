<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(400);
    exit('A valid article slug is required.');
}
$statement = db()->prepare(
    "SELECT title, summary, content, published_at
     FROM news_articles WHERE slug = :slug AND status = 'published'
        AND published_at <= CURRENT_TIMESTAMP LIMIT 1"
);
$statement->execute(['slug' => $slug]);
$article = $statement->fetch();
if ($article === false) {
    http_response_code(404);
    exit('Article not found.');
}
$pageTitle = $article['title'];
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <article class="card">
        <p class="eyebrow"><?= e($article['published_at']) ?></p>
        <h1><?= e($article['title']) ?></h1>
        <p><strong><?= e($article['summary'] ?? '') ?></strong></p>
        <div><?= nl2br(e($article['content'])) ?></div>
    </article>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
