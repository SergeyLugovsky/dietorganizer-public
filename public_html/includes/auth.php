<?php
function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Пожалуйста, войдите, чтобы продолжить.');
        redirect('/login');
    }
}

function log_in_user(array $user): void
{
    $_SESSION['user'] = $user;
}

function log_out_user(): void
{
    unset($_SESSION['user']);
}
