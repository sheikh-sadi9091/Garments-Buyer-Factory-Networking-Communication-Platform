<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function current_company(): ?array
{
    $user = current_user();
    if ($user === null) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, owner_user_id, name, description, location, website, phone, logo_path, cover_image_path,
                capacity_per_month, moq, lead_time_days, verification_status
         FROM companies WHERE owner_user_id = :user_id LIMIT 1'
    );
    $statement->execute(['user_id' => $user['id']]);
    $company = $statement->fetch();
    return $company === false ? null : $company;
}

function money_format_value($amount, string $currency): string
{
    if ($amount === null) {
        return 'Price on request';
    }
    return e($currency) . ' ' . number_format((float) $amount, 2);
}

function date_input_is_valid(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function is_valid_http_url(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
}

function can_message_user(int $userId, int $partnerId): bool
{
    if ($userId < 1 || $partnerId < 1 || $userId === $partnerId) {
        return false;
    }

    $statement = db()->prepare(
        "SELECT 1
         FROM users current_account
         INNER JOIN roles AS role_current ON role_current.id = current_account.role_id
         INNER JOIN users partner_user ON partner_user.id = :partner_id
         INNER JOIN roles partner_role ON partner_role.id = partner_user.role_id
         WHERE current_account.id = :current_user_id
           AND (
             (role_current.slug = 'buyer' AND partner_role.slug = 'buying_house' AND EXISTS (
                 SELECT 1 FROM rfqs
                 INNER JOIN rfq_recipients ON rfq_recipients.rfq_id = rfqs.id
                 INNER JOIN companies ON companies.id = rfq_recipients.recipient_company_id
                 WHERE rfqs.buyer_id = current_account.id AND companies.owner_user_id = partner_user.id
             ))
             OR (role_current.slug = 'buying_house' AND partner_role.slug = 'buyer' AND EXISTS (
                 SELECT 1 FROM rfqs
                 INNER JOIN rfq_recipients ON rfq_recipients.rfq_id = rfqs.id
                 INNER JOIN companies ON companies.id = rfq_recipients.recipient_company_id
                 WHERE rfqs.buyer_id = partner_user.id AND companies.owner_user_id = current_account.id
             ))
             OR (role_current.slug = 'buying_house' AND partner_role.slug IN ('factory', 'supplier') AND EXISTS (
                 SELECT 1 FROM buying_house_factories
                 INNER JOIN companies house ON house.id = buying_house_factories.buying_house_company_id
                 INNER JOIN companies factory ON factory.id = buying_house_factories.factory_company_id
                 WHERE house.owner_user_id = current_account.id AND factory.owner_user_id = partner_user.id
             ))
             OR (role_current.slug IN ('factory', 'supplier') AND partner_role.slug = 'buying_house' AND EXISTS (
                 SELECT 1 FROM buying_house_factories
                 INNER JOIN companies house ON house.id = buying_house_factories.buying_house_company_id
                 INNER JOIN companies factory ON factory.id = buying_house_factories.factory_company_id
                 WHERE factory.owner_user_id = current_account.id AND house.owner_user_id = partner_user.id
             ))
           )
         LIMIT 1"
    );
    $statement->execute(['partner_id' => $partnerId, 'current_user_id' => $userId]);
    return $statement->fetchColumn() !== false;
}

function save_image_upload(array $file, string $subdirectory): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new InvalidArgumentException('The image upload failed. Choose the file again.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Images must be no larger than 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new InvalidArgumentException('Only JPEG, PNG, and WebP images are allowed.');
    }
    $safeDirectory = trim(str_replace('\\', '/', $subdirectory), '/');
    if ($safeDirectory === '' || !preg_match('/^[a-z0-9/_-]+$/', $safeDirectory)) {
        throw new InvalidArgumentException('Invalid upload destination.');
    }
    $absoluteDirectory = dirname(__DIR__) . '/uploads/' . $safeDirectory;
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0750, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('Unable to create the image upload directory.');
    }
    if (!is_writable($absoluteDirectory)) {
        throw new RuntimeException('The image upload directory is not writable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $absoluteDirectory . '/' . $filename)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }
    return 'uploads/' . $safeDirectory . '/' . $filename;
}
