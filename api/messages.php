<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/domain.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function message_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$user = current_user();
$partnerId = filter_var($_GET['with'] ?? $_POST['with'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId === null || $partnerId < 1 || !can_message_user((int) $user['id'], $partnerId)) {
    message_response(403, ['error' => 'This conversation is not available.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $markRead = db()->prepare(
        'UPDATE messages SET read_at = CURRENT_TIMESTAMP
         WHERE sender_id = :partner_id AND receiver_id = :current_user AND read_at IS NULL'
    );
    $markRead->execute(['partner_id' => $partnerId, 'current_user' => $user['id']]);
    $statement = db()->prepare(
        'SELECT id, sender_id, receiver_id, body, attachment_name, sent_at
         FROM messages
         WHERE (sender_id = :current_sender AND receiver_id = :current_receiver)
            OR (sender_id = :other_sender AND receiver_id = :other_receiver)
         ORDER BY sent_at DESC, id DESC LIMIT 100'
    );
    $statement->execute([
        'current_sender' => $user['id'],
        'current_receiver' => $partnerId,
        'other_sender' => $partnerId,
        'other_receiver' => $user['id'],
    ]);
    $messages = array_reverse($statement->fetchAll());
    foreach ($messages as &$message) {
        $message['id'] = (int) $message['id'];
        $message['sender_id'] = (int) $message['sender_id'];
        $message['download_url'] = $message['attachment_name'] !== null
            ? app_url('api/message-download.php?id=' . $message['id'])
            : null;
    }
    unset($message);
    message_response(200, ['messages' => $messages]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    message_response(405, ['error' => 'Method not allowed.']);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    message_response(400, ['error' => 'Your session expired. Reload the page and try again.']);
}

$body = trim((string) ($_POST['body'] ?? ''));
if (strlen($body) > 10000) {
    message_response(400, ['error' => 'Messages must be 10,000 characters or fewer.']);
}

$attachmentPath = null;
$attachmentName = null;
$attachmentMime = null;
if (isset($_FILES['attachment']) && is_array($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        message_response(400, ['error' => 'The attachment upload failed. Try again with a different file.']);
    }
    if ($file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
        message_response(400, ['error' => 'Attachments must be no larger than 10 MB.']);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($allowedTypes[$mime])) {
        message_response(400, ['error' => 'Only PDF, JPEG, PNG, and WebP files are allowed.']);
    }
    $uploadDirectory = __DIR__ . '/../uploads/chat';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the chat upload directory.');
    }
    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The chat upload directory is not writable.');
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $storedName)) {
        throw new RuntimeException('Unable to save the uploaded attachment.');
    }
    $attachmentPath = 'uploads/chat/' . $storedName;
    $attachmentName = basename((string) $file['name']);
    $attachmentMime = $mime;
}

if ($body === '' && $attachmentPath === null) {
    message_response(400, ['error' => 'Write a message or attach a file before sending.']);
}

$statement = db()->prepare(
    'INSERT INTO messages
        (sender_id, receiver_id, body, attachment_path, attachment_name, attachment_mime)
     VALUES
        (:sender_id, :receiver_id, :body, :attachment_path, :attachment_name, :attachment_mime)'
);
$statement->execute([
    'sender_id' => $user['id'],
    'receiver_id' => $partnerId,
    'body' => $body,
    'attachment_path' => $attachmentPath,
    'attachment_name' => $attachmentName,
    'attachment_mime' => $attachmentMime,
]);

$notification = db()->prepare(
    'INSERT INTO notifications (user_id, type, title, body, target_url)
     VALUES (:user_id, :type, :title, :body, :target_url)'
);
$notification->execute([
    'user_id' => $partnerId,
    'type' => 'new_message',
    'title' => 'New message',
    'body' => 'You received a new sourcing message.',
    'target_url' => '/messages/index.php?with=' . (int) $user['id'],
]);
message_response(201, ['message' => 'Message sent.']);
