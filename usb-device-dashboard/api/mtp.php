<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? 'auto';

switch ($action) {
	case 'detect':
	default:
		respond_ok(mtp_detect_info());
}