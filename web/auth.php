<?php

function app_role(): ?string
{
    $r = $_SESSION['app_role'] ?? null;

    return is_string($r) ? $r : null;
}

function require_staff(): void
{
    if (app_role() !== 'employee') {
        require __DIR__ . '/header.php';
        echo '<p class="err">Staff only. <a href="index.php">Start page</a></p>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function require_customer(): void
{
    if (app_role() !== 'customer') {
        require __DIR__ . '/header.php';
        echo '<p class="err">Customers only. <a href="index.php">Start page</a></p>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
