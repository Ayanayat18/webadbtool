<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? 'auto';

switch ($action) {
	case 'ports':
		respond_ok(detect_samsung_com_ports());
		break;
	case 'probe':
		$port = $_GET['port'] ?? null;
		$data = heimdall_print_pit($port);
		respond_ok(['port' => $port, 'output' => $data]);
		break;
	case 'auto':
	default:
		$ports = detect_samsung_com_ports();
		if (count($ports) > 0) {
			$port = $ports[0]['port'];
			$data = heimdall_print_pit($port);
			respond_ok(['port' => $port, 'output' => $data]);
		} else {
			respond_ok(['port' => null, 'output' => ['stdout' => '', 'stderr' => '']]);
		}
}