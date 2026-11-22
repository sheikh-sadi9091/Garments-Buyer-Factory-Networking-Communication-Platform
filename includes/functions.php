<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $base = '';
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($projectRoot !== false && $scriptFile !== false) {
        $normalizedRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $normalizedScript = str_replace('\\', '/', $scriptFile);
        $rootPrefix = $normalizedRoot . '/';
        if (stripos($normalizedScript, $rootPrefix) === 0) {
            $relativeScript = substr($normalizedScript, strlen($rootPrefix));
            if ($relativeScript !== '' && strlen($scriptName) >= strlen($relativeScript)) {
                $scriptSuffix = substr($scriptName, -strlen($relativeScript));
                if (strcasecmp($scriptSuffix, $relativeScript) === 0) {
                    $base = rtrim(substr($scriptName, 0, -strlen($relativeScript)), '/');
                }
            }
        }

        if ($base === '') {
            $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
            $normalizedDocumentRoot = $documentRoot === false
                ? ''
                : rtrim(str_replace('\\', '/', $documentRoot), '/');
            $normalizedProject = str_replace('\\', '/', $projectRoot);
            if ($normalizedDocumentRoot !== '' && strpos($normalizedProject, $normalizedDocumentRoot . '/') === 0) {
                $base = substr($normalizedProject, strlen($normalizedDocumentRoot));
            }
        }
    }

    $path = ltrim($path, '/');
    return $base . ($path === '' ? '' : '/' . $path);
}

function redirect_to(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('garments_network_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

start_app_session();
