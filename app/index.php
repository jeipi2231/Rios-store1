<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/app/dashboard.php');
    exit;
}

header('Location: ' . BASE_URL . '/app/auth/login.php');
exit;
