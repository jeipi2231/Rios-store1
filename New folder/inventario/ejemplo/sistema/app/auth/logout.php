<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

session_destroy();
redirect('/app/auth/login.php');
