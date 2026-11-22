<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_role('buying_house', 'factory', 'supplier');

$user = current_user();
$company = current_company();
if ($user === null || $company === null) {
    http_response_code(404);
    exit('Company profile was not found.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    http_response_code(400);
    exit('Invalid certificate submission.');
}

$name = trim((string) ($_POST['certificate_name'] ?? ''));
$number = trim((string) ($_POST['certificate_number'] ?? ''));
$expiresOn = trim((string) ($_POST['expires_on'] ?? ''));
if ($name === '' || strlen($name) > 120 || strlen($number) > 120) {
    http_response_code(400);
    exit('Enter a certificate name and a valid certificate number.');
}
if ($expiresOn !== '' && !date_input_is_valid($expiresOn)) {
    http_response_code(400);
    exit('Enter a valid certificate expiry date.');
}
$file = $_FILES['certificate_file'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
    http_response_code(400);
    exit('Choose a certificate PDF, JPEG, or PNG file to upload.');
}
if ($file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    exit('Certificate files must be no larger than 10 MB.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!is_string($mime) || !isset($extensions[$mime])) {
    http_response_code(400);
    exit('Only PDF, JPEG, and PNG certificates are allowed.');
}
$uploadDirectory = __DIR__ . '/../uploads/certificates';
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
    throw new RuntimeException('Unable to create the certificate upload directory.');
}
if (!is_writable($uploadDirectory)) {
    throw new RuntimeException('The certificate upload directory is not writable.');
}
$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
    throw new RuntimeException('Unable to save the uploaded certificate.');
}
$relativePath = 'uploads/certificates/' . $filename;

$pdo = db();
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO company_certificates
            (company_id, certificate_name, certificate_number, expires_on, file_path, status)
         VALUES
            (:company_id, :certificate_name, :certificate_number, :expires_on, :file_path, \'pending\')'
    );
    $insert->execute([
        'company_id' => $company['id'],
        'certificate_name' => $name,
        'certificate_number' => $number !== '' ? $number : null,
        'expires_on' => $expiresOn !== '' ? $expiresOn : null,
        'file_path' => $relativePath,
    ]);

    $pdo->prepare(
        "UPDATE companies SET verification_status = 'pending', verification_remarks = NULL
         WHERE id = :company_id"
    )->execute(['company_id' => $company['id']]);
    $pdo->prepare(
        'INSERT INTO verification_requests (company_id, requested_by, status)
         VALUES (:company_id, :requested_by, \'pending\')'
    )->execute(['company_id' => $company['id'], 'requested_by' => $user['id']]);

    $admins = $pdo->query(
        "SELECT users.id FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'admin' AND users.status = 'active'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $notification = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, target_url)
         VALUES (:user_id, :type, :title, :body, :target_url)'
    );
    foreach ($admins as $adminId) {
        $notification->execute([
            'user_id' => $adminId,
            'type' => 'verification_request',
            'title' => 'A company submitted verification documents',
            'body' => $company['name'] . ' submitted ' . $name . ' for review.',
            'target_url' => '/admin/index.php',
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($uploadDirectory . '/' . $filename) && !unlink($uploadDirectory . '/' . $filename)) {
        throw new RuntimeException('The certificate upload could not be cleaned up after a database error.', 0, $exception);
    }
    throw $exception;
}

set_flash('success', 'Certificate submitted. An administrator will review your company verification.');
redirect_to(app_url('companies/profile.php'));
