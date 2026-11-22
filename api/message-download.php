<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

$messageId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$user = current_user();
if ($messageId === false || $messageId === null || $messageId < 1) {
    http_response_code(400);
    exit('A valid message ID is required.');
}
$statement = db()->prepare(
    'SELECT id, sender_id, receiver_id, attachment_path, attachment_name, attachment_mime
     FROM messages
     WHERE id = :message_id AND (sender_id = :sender_user OR receiver_id = :receiver_user)
     LIMIT 1'
);
$statement->execute([
    'message_id' => $messageId,
    'sender_user' => $user['id'],
    'receiver_user' => $user['id'],
]);
$message = $statement->fetch();
if ($message === false || $message['attachment_path'] === null
    || !can_message_user((int) $user['id'], (int) ($message['sender_id'] === $user['id'] ? $message['receiver_id'] : $message['sender_id']))) {
    http_response_code(404);
    exit('Attachment not found.');
}

$uploadDirectory = realpath(__DIR__ . '/../uploads/chat');
$storedName = basename($message['attachment_path']);
$filePath = $uploadDirectory === false ? false : realpath($uploadDirectory . DIRECTORY_SEPARATOR . $storedName);
if ($uploadDirectory === false || $filePath === false
    || strpos($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
if (!in_array($message['attachment_mime'], $allowedMimes, true)) {
    http_response_code(415);
    exit('Unsupported attachment type.');
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $message['attachment_mime']);
header('Content-Length: ' . (string) filesize($filePath));
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $message['attachment_name']);
header('Content-Disposition: attachment; filename="' . ($downloadName !== '' ? $downloadName : 'attachment') . '"');
readfile($filePath);
