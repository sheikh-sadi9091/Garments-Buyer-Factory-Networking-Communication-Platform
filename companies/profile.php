<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buyer', 'buying_house', 'factory', 'supplier');

$user = current_user();
$company = current_company();
if ($user === null || $company === null) {
    http_response_code(404);
    exit('Company profile was not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Your form session expired. Reload the page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $website = trim((string) ($_POST['website'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $capacity = trim((string) ($_POST['capacity_per_month'] ?? ''));
        $moq = trim((string) ($_POST['moq'] ?? ''));
        $leadTime = trim((string) ($_POST['lead_time_days'] ?? ''));

        if ($name === '' || strlen($name) > 190) {
            $errors[] = 'Company name is required and must be at most 190 characters.';
        }
        if ($location === '' || strlen($location) > 190) {
            $errors[] = 'Location is required and must be at most 190 characters.';
        }
        if (strlen($description) > 10000) {
            $errors[] = 'Description is too long.';
        }
        if ($website !== '' && (!is_valid_http_url($website) || strlen($website) > 255)) {
            $errors[] = 'Enter a valid website URL.';
        }
        if (strlen($phone) > 40) {
            $errors[] = 'Phone number must be at most 40 characters.';
        }

        $numericFields = [
            'capacity_per_month' => ['value' => $capacity, 'maximum' => 4294967295, 'label' => 'Monthly capacity'],
            'moq' => ['value' => $moq, 'maximum' => 4294967295, 'label' => 'MOQ'],
            'lead_time_days' => ['value' => $leadTime, 'maximum' => 65535, 'label' => 'Lead time'],
        ];
        $validated = [];
        foreach ($numericFields as $key => $field) {
            if ($field['value'] === '') {
                $validated[$key] = null;
                continue;
            }
            if (!ctype_digit($field['value']) || (float) $field['value'] > $field['maximum']) {
                $errors[] = $field['label'] . ' must be a non-negative whole number.';
                continue;
            }
            $validated[$key] = (int) $field['value'];
        }

        $uploadedImages = [];
        if ($errors === []) {
            foreach (['logo' => 'logo_path', 'cover_image' => 'cover_image_path'] as $field => $column) {
                try {
                    $uploadedImages[$column] = save_image_upload($_FILES[$field] ?? [], 'companies');
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        if ($errors === []) {
            $statement = db()->prepare(
                'UPDATE companies SET name = :name, location = :location, description = :description,
                    website = :website, phone = :phone, capacity_per_month = :capacity_per_month,
                    moq = :moq, lead_time_days = :lead_time_days,
                    logo_path = COALESCE(:logo_path, logo_path),
                    cover_image_path = COALESCE(:cover_image_path, cover_image_path)
                 WHERE id = :company_id AND owner_user_id = :owner_user_id'
            );
            $statement->execute([
                'name' => $name,
                'location' => $location,
                'description' => $description !== '' ? $description : null,
                'website' => $website !== '' ? $website : null,
                'phone' => $phone !== '' ? $phone : null,
                'capacity_per_month' => $validated['capacity_per_month'],
                'moq' => $validated['moq'],
                'lead_time_days' => $validated['lead_time_days'],
                'logo_path' => $uploadedImages['logo_path'] ?? null,
                'cover_image_path' => $uploadedImages['cover_image_path'] ?? null,
                'company_id' => $company['id'],
                'owner_user_id' => $user['id'],
            ]);
            set_flash('success', 'Company profile updated.');
            redirect_to(app_url('companies/profile.php'));
        }

        $company = array_merge($company, [
            'name' => $name,
            'location' => $location,
            'description' => $description,
            'website' => $website,
            'phone' => $phone,
            'capacity_per_month' => $capacity,
            'moq' => $moq,
            'lead_time_days' => $leadTime,
        ]);
    }
}

$certificates = db()->prepare(
    'SELECT certificate_name, certificate_number, expires_on, status
     FROM company_certificates WHERE company_id = :company_id ORDER BY created_at DESC'
);
$certificates->execute(['company_id' => $company['id']]);
$certificates = $certificates->fetchAll();
$expiryNotice = db()->prepare(
    "SELECT certificate_name, expires_on FROM company_certificates
     WHERE company_id = :company_id AND status = 'approved'
       AND expires_on BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
     ORDER BY expires_on"
);
$expiryNotice->execute(['company_id' => $company['id']]);
$expiringCertificates = $expiryNotice->fetchAll();

$pageTitle = 'Company profile';
require __DIR__ . '/../includes/header.php';
?>
<main class="container narrow">
    <section class="form-card">
        <p class="eyebrow">Company settings</p>
        <h1>Company profile</h1>
        <p>Verification status: <span class="status-pill status-<?= e($company['verification_status']) ?>"><?= e(ucfirst($company['verification_status'])) ?></span></p>
        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(app_url('companies/profile.php')) ?>" data-validate>
            <?= csrf_field() ?>
            <label for="name">Company name</label>
            <input id="name" name="name" maxlength="190" required value="<?= e($company['name']) ?>">
            <label for="location">Location</label>
            <input id="location" name="location" maxlength="190" required value="<?= e($company['location']) ?>">
            <label for="description">Company description</label>
            <textarea id="description" name="description" rows="5" maxlength="10000"><?= e($company['description']) ?></textarea>
            <label for="website">Website</label>
            <input id="website" name="website" type="url" maxlength="255" value="<?= e($company['website']) ?>">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" maxlength="40" value="<?= e($company['phone']) ?>">
            <label for="logo">Company logo (JPEG, PNG, or WebP; maximum 5 MB)</label>
            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
            <label for="cover_image">Cover image (JPEG, PNG, or WebP; maximum 5 MB)</label>
            <input id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/webp">
            <label for="capacity_per_month">Monthly capacity (units)</label>
            <input id="capacity_per_month" name="capacity_per_month" type="number" min="0" value="<?= e((string) $company['capacity_per_month']) ?>">
            <label for="moq">Minimum order quantity (MOQ)</label>
            <input id="moq" name="moq" type="number" min="0" value="<?= e((string) $company['moq']) ?>">
            <label for="lead_time_days">Lead time (days)</label>
            <input id="lead_time_days" name="lead_time_days" type="number" min="0" max="65535" value="<?= e((string) $company['lead_time_days']) ?>">
            <button class="button button-full" type="submit">Save profile</button>
        </form>
    </section>
    <section class="card admin-section">
        <h2>Certificates</h2>
        <?php if ($expiringCertificates !== []): ?><div class="alert alert-warning" role="status"><strong>Certificate expiry reminder:</strong>
            <ul><?php foreach ($expiringCertificates as $certificate): ?><li><?= e($certificate['certificate_name']) ?> expires <?= e($certificate['expires_on']) ?>.</li><?php endforeach; ?></ul>
        </div><?php endif; ?>
        <?php if ($certificates === []): ?>
            <p>No certificates have been added yet.</p>
        <?php else: ?>
            <ul><?php foreach ($certificates as $certificate): ?>
                <li><?= e($certificate['certificate_name']) ?> —
                    <span class="status-pill"><?= e(ucfirst($certificate['status'])) ?></span>
                    <?php if ($certificate['expires_on'] !== null): ?>, expires <?= e($certificate['expires_on']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['buying_house', 'factory', 'supplier'], true)): ?>
            <h3>Submit a certificate for verification</h3>
            <form method="post" enctype="multipart/form-data" action="<?= e(app_url('companies/certificate.php')) ?>">
                <?= csrf_field() ?>
                <label for="certificate_name">Certificate name</label><input id="certificate_name" name="certificate_name" maxlength="120" required>
                <label for="certificate_number">Certificate number</label><input id="certificate_number" name="certificate_number" maxlength="120">
                <label for="expires_on">Expiry date</label><input id="expires_on" name="expires_on" type="date">
                <label for="certificate_file">Certificate file (PDF, JPEG, or PNG; maximum 10 MB)</label>
                <input id="certificate_file" name="certificate_file" type="file" accept="application/pdf,image/jpeg,image/png" required>
                <button class="button" type="submit">Submit for verification</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
