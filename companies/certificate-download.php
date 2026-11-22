<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$certificateId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($certificateId === false || $certificateId === null || $certificateId < 1) {
    http_response_code(400);
    exit('A valid certificate ID is required.');
}
$statement = db()->prepare(
    'SELECT file_path, certificate_name
     FROM company_certificates WHERE id = :id LIMIT 1'
);
$statement->execute(['id' => $certificateId]);
$certificate = $statement->fetch();
if ($certificate === false || $certificate['file_path'] === null) {
    http_response_code(404);
    exit('Certificate document not found.');
}
$uploadRoot = realpath(__DIR__ . '/../uploads/certificates');
$fileName = basename($certificate['file_path']);
$filePath = $uploadRoot === false ? false : realpath($uploadRoot . DIRECTORY_SEPARATOR . $fileName);
if ($uploadRoot === false || $filePath === false
    || strpos($filePath, $uploadRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Certificate document not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Unsupported certificate document type.');
}
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="certificate-' . (int) $certificateId . '.' . $extension . '"');
readfile($filePath);
