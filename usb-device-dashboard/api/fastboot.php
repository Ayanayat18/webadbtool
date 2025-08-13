<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? 'auto';

switch ($action) {
	case 'devices':
		respond_ok(detect_fastboot_device());
		break;
	case 'getvars':
		$serial = $_GET['serial'] ?? null;
		$vars = fastboot_getvars($serial);
		respond_ok(['serial' => $serial, 'vars' => $vars, 'summary' => extract_fastboot_summary($vars)]);
		break;
	case 'auto':
	default:
		$devices = detect_fastboot_device();
		if (count($devices) > 0) {
			$serial = $devices[0]['serial'];
			$vars = fastboot_getvars($serial);
			respond_ok(['serial' => $serial, 'vars' => $vars, 'summary' => extract_fastboot_summary($vars)]);
		} else {
			respond_ok(['serial' => null, 'vars' => [], 'summary' => []]);
		}
}