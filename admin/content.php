<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $type = (string) ($_POST['type'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($title === '' || strlen($title) > 190) {
            $errors[] = 'Enter a title of at most 190 characters.';
        }
        if (strlen($description) > 20000) {
            $errors[] = 'The description must be at most 20,000 characters.';
        }
        $status = isset($_POST['publish']) ? 'published' : 'draft';

        if ($type === 'event') {
            $venue = trim((string) ($_POST['venue'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
            $endsAt = trim((string) ($_POST['ends_at'] ?? ''));
            $registrationUrl = trim((string) ($_POST['registration_url'] ?? ''));
            $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startsAt);
            $parsedEnd = $endsAt !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endsAt) : null;
            if ($parsedStart === false || ($endsAt !== '' && $parsedEnd === false)) {
                $errors[] = 'Enter a valid event start and end date.';
            }
            if ($parsedStart !== false && $parsedEnd instanceof DateTimeImmutable && $parsedEnd <= $parsedStart) {
                $errors[] = 'Event end time must be after its start.';
            }
            if (strlen($venue) > 190 || strlen($location) > 190) {
                $errors[] = 'Venue and location must be at most 190 characters.';
            }
            if ($registrationUrl !== '' && (!is_valid_http_url($registrationUrl) || strlen($registrationUrl) > 255)) {
                $errors[] = 'Enter a valid registration URL.';
            }
            if ($errors === []) {
                $insert = db()->prepare(
                    'INSERT INTO events
                        (title, description, venue, location, starts_at, ends_at, registration_url, status, created_by)
                     VALUES
                        (:title, :description, :venue, :location, :starts_at, :ends_at, :registration_url, :status, :created_by)'
                );
                $insert->execute([
                    'title' => $title,
                    'description' => $description !== '' ? $description : null,
                    'venue' => $venue !== '' ? $venue : null,
                    'location' => $location !== '' ? $location : null,
                    'starts_at' => $parsedStart->format('Y-m-d H:i:s'),
                    'ends_at' => $parsedEnd instanceof DateTimeImmutable ? $parsedEnd->format('Y-m-d H:i:s') : null,
                    'registration_url' => $registrationUrl !== '' ? $registrationUrl : null,
                    'status' => $status,
                    'created_by' => current_user()['id'],
                ]);
                set_flash('success', 'Event ' . $status . '.');
                redirect_to(app_url('admin/content.php'));
            }
        } elseif ($type === 'news') {
            $summary = trim((string) ($_POST['summary'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            if ($summary !== '' && strlen($summary) > 5000) {
                $errors[] = 'The article summary is too long.';
            }
            if ($content === '' || strlen($content) > 200000) {
                $errors[] = 'Article content is required and must be at most 200,000 characters.';
            }
            if ($errors === []) {
                $slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
                if ($slugBase === '') {
                    $errors[] = 'The article title must contain letters or numbers.';
                } else {
                    $slug = $slugBase . '-' . bin2hex(random_bytes(3));
                    $insert = db()->prepare(
                        'INSERT INTO news_articles
                            (title, slug, summary, content, status, author_id, published_at)
                         VALUES
                            (:title, :slug, :summary, :content, :status, :author_id, :published_at)'
                    );
                    $insert->execute([
                        'title' => $title,
                        'slug' => $slug,
                        'summary' => $summary !== '' ? $summary : null,
                        'content' => $content,
                        'status' => $status,
                        'author_id' => current_user()['id'],
                        'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
                    ]);
                    set_flash('success', 'News article ' . $status . '.');
                    redirect_to(app_url('admin/content.php'));
                }
            }
        } else {
            http_response_code(400);
            $errors[] = 'Select an event or news article.';
        }
    }
}

$events = db()->query('SELECT id, title, starts_at, status FROM events ORDER BY starts_at DESC LIMIT 50')->fetchAll();
$articles = db()->query('SELECT id, title, slug, status, published_at FROM news_articles ORDER BY created_at DESC LIMIT 50')->fetchAll();

$pageTitle = 'Manage events and news';
require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading"><p class="eyebrow">Platform administration</p><h1>Events and news</h1><p>Create an event or publish a sourcing community update.</p></section>
    <?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <section class="feature-grid">
        <article class="card"><h2>Create event</h2><form method="post" action="<?= e(app_url('admin/content.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="type" value="event">
            <label>Event title<input name="title" maxlength="190" required></label>
            <label>Description<textarea name="description" rows="4" maxlength="20000"></textarea></label>
            <label>Venue<input name="venue" maxlength="190"></label><label>Location<input name="location" maxlength="190"></label>
            <label>Starts<input name="starts_at" type="datetime-local" required></label><label>Ends<input name="ends_at" type="datetime-local"></label>
            <label>Registration URL<input name="registration_url" type="url" maxlength="255"></label>
            <label><input name="publish" type="checkbox" value="1"> Publish immediately</label>
            <button class="button" type="submit">Save event</button>
        </form></article>
        <article class="card"><h2>Create news article</h2><form method="post" action="<?= e(app_url('admin/content.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="type" value="news">
            <label>Title<input name="title" maxlength="190" required></label>
            <label>Summary<textarea name="summary" rows="3" maxlength="5000"></textarea></label>
            <label>Article content<textarea name="content" rows="7" maxlength="200000" required></textarea></label>
            <label><input name="publish" type="checkbox" value="1"> Publish immediately</label>
            <button class="button" type="submit">Save article</button>
        </form></article>
    </section>
    <section class="feature-grid">
        <article class="card"><h2>Events</h2><?php if ($events === []): ?><p>No events yet.</p><?php else: ?><ul><?php foreach ($events as $event): ?><li><?= e($event['title']) ?> · <?= e($event['starts_at']) ?> · <?= e(ucfirst($event['status'])) ?></li><?php endforeach; ?></ul><?php endif; ?></article>
        <article class="card"><h2>News articles</h2><?php if ($articles === []): ?><p>No articles yet.</p><?php else: ?><ul><?php foreach ($articles as $article): ?><li><?= e($article['title']) ?> · <?= e(ucfirst($article['status'])) ?></li><?php endforeach; ?></ul><?php endif; ?></article>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
