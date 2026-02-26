<?php
// public_html/includes/helpers.php
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string
{
    if (!isset($_SESSION['__flash'])) {
        $_SESSION['__flash'] = [];
    }

    if ($message === null) {
        if (!isset($_SESSION['__flash'][$key])) {
            return null;
        }

        $msg = $_SESSION['__flash'][$key];
        unset($_SESSION['__flash'][$key]);

        return $msg;
    }

    $_SESSION['__flash'][$key] = $message;
    return null;
}

function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
