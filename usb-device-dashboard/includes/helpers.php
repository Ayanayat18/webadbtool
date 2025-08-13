<?php

declare(strict_types=1);

function app_config(): array {
	static $config;
	if ($config === null) {
		$config = require __DIR__ . '/../config.php';
	}
	return $config;
}

function is_windows(): bool {
	return DIRECTORY_SEPARATOR === '\\' || stripos(PHP_OS_FAMILY, 'Windows') !== false;
}

function safe_exec(array $commandParts, int $timeoutSeconds = null): array {
	$config = app_config();
	if ($timeoutSeconds === null) {
		$timeoutSeconds = (int)($config['app']['default_timeout_seconds'] ?? 12);
	}

	$cmd = build_command_string($commandParts);

	$descriptorspec = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	$env = null;
	$cwd = $config['paths']['root_dir'] ?? null;
	$process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);
	if (!is_resource($process)) {
		return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Failed to start process'];
	}

	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);
	fclose($pipes[0]);

	$stdout = '';
	$stderr = '';
	$start = time();
	while (true) {
		$stdout .= stream_get_contents($pipes[1]);
		$stderr .= stream_get_contents($pipes[2]);

		$status = proc_get_status($process);
		if (!$status['running']) {
			break;
		}
		if ((time() - $start) > $timeoutSeconds) {
			proc_terminate($process);
			return ['exit_code' => 124, 'stdout' => $stdout, 'stderr' => 'Timeout'];
		}
		usleep(50_000);
	}

	$exitCode = proc_close($process);
	return ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
}

function build_command_string(array $parts): string {
	$escaped = [];
	foreach ($parts as $part) {
		$escaped[] = escapeshellarg((string)$part);
	}
	$cmd = implode(' ', $escaped);
	if (is_windows()) {
		return 'cmd /C ' . $cmd;
	}
	return $cmd;
}

function json_response(array $data, int $statusCode = 200): void {
	header('Content-Type: application/json');
	http_response_code($statusCode);
	echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit;
}

function detect_fastboot_device(): array {
	$config = app_config();
	$fastbootPath = $config['paths']['fastboot'];
	$result = safe_exec([$fastbootPath, 'devices', '-l']);
	return parse_fastboot_devices($result['stdout']);
}

function parse_fastboot_devices(string $stdout): array {
	$devices = [];
	$lines = preg_split('/\r?\n/', trim($stdout));
	foreach ($lines as $line) {
		if ($line === '' || stripos($line, 'fastboot') === false) {
			continue;
		}
		$parts = preg_split('/\s+/', trim($line));
		$serial = $parts[0] ?? '';
		$devices[] = [
			'serial' => $serial,
			'raw' => $line,
		];
	}
	return $devices;
}

function fastboot_getvars(string $serial = null): array {
	$config = app_config();
	$fastbootPath = $config['paths']['fastboot'];
	$parts = [$fastbootPath, 'getvar', 'all'];
	if ($serial) {
		array_splice($parts, 1, 0, ['-s', $serial]);
	}
	$result = safe_exec($parts);
	return parse_fastboot_getvar_output($result['stdout'] . "\n" . $result['stderr']);
}

function parse_fastboot_getvar_output(string $output): array {
	$info = [];
	$lines = preg_split('/\r?\n/', $output);
	foreach ($lines as $line) {
		if (preg_match('/^\s*\(bootloader\)\s*(.+)$/i', $line, $m)) {
			$line = trim($m[1]);
		}
		if (preg_match('/^(.+?):\s*(.*)$/', $line, $m)) {
			$key = trim($m[1]);
			$val = trim($m[2]);
			$info[$key] = $val;
		}
	}
	return $info;
}

function adb_list_devices(): array {
	$config = app_config();
	$adb = $config['paths']['adb'];
	$result = safe_exec([$adb, 'devices']);
	return parse_adb_devices($result['stdout']);
}

function parse_adb_devices(string $stdout): array {
	$devices = [];
	$lines = preg_split('/\r?\n/', trim($stdout));
	foreach ($lines as $line) {
		if ($line === '' || stripos($line, 'List of devices') !== false) {
			continue;
		}
		$parts = preg_split('/\s+/', trim($line));
		if (count($parts) >= 2 && in_array($parts[1], ['device', 'authorized', 'unauthorized', 'offline'], true)) {
			$devices[] = [
				'id' => $parts[0],
				'status' => $parts[1],
			];
		}
	}
	return $devices;
}

function adb_get_properties(string $deviceId): array {
	$config = app_config();
	$adb = $config['paths']['adb'];
	$result = safe_exec([$adb, '-s', $deviceId, 'shell', 'getprop']);
	return parse_getprop_output($result['stdout']);
}

function parse_getprop_output(string $stdout): array {
	$props = [];
	$lines = preg_split('/\r?\n/', trim($stdout));
	foreach ($lines as $line) {
		if (preg_match('/^\[(.+?)\]: \[(.*)\]$/', trim($line), $m)) {
			$props[$m[1]] = $m[2];
		}
	}
	return $props;
}

function mtp_detect_info(): array {
	$config = app_config();
	$mtp = $config['paths']['mtp_detect'];
	$result = safe_exec([$mtp]);
	return ['raw' => $result['stdout']];
}

function detect_samsung_com_ports(): array {
	if (is_windows()) {
		// Windows: enumerate COM ports via mode command
		$result = safe_exec(['mode']);
		return parse_windows_com_ports($result['stdout']);
	}
	// Linux: scan /dev/tty* patterns commonly used
	$ports = glob('/dev/ttyUSB*');
	$ports = array_merge($ports ?: [], glob('/dev/ttyACM*') ?: []);
	$ports = array_values(array_unique($ports));
	return array_map(fn($p) => ['port' => $p], $ports);
}

function parse_windows_com_ports(string $stdout): array {
	$ports = [];
	if (preg_match_all('/Status for device (COM\d+)/i', $stdout, $m)) {
		foreach ($m[1] as $port) {
			$ports[] = ['port' => $port];
		}
	}
	return $ports;
}

function heimdall_print_pit(string $port = null): array {
	$config = app_config();
	$heimdall = $config['paths']['heimdall'];
	$parts = [$heimdall, 'print-pit', '--no-reboot'];
	if ($port) {
		$parts[] = '--usb-port';
		$parts[] = $port;
	}
	$result = safe_exec($parts, 20);
	return ['stdout' => $result['stdout'], 'stderr' => $result['stderr']];
}

function extract_fastboot_summary(array $vars): array {
	return [
		'Device Model' => $vars['product'] ?? ($vars['variant'] ?? ''),
		'Product Name' => $vars['product'] ?? '',
		'Serial Number' => $vars['serialno'] ?? ($vars['serial'] ?? ''),
		'Bootloader' => $vars['version-bootloader'] ?? '',
		'Device State' => $vars['device-state'] ?? '',
	];
}

function extract_adb_summary(array $props): array {
	return [
		'Device Name' => $props['ro.product.model'] ?? '',
		'Serial Number' => $props['ro.serialno'] ?? '',
		'Android Version' => $props['ro.build.version.release'] ?? '',
		'IMEI' => $props['ril.gsm.imei'] ?? ($props['persist.radio.imei'] ?? ''),
		'Storage (total)' => $props['sys.storage.total'] ?? '',
	];
}

function respond_ok(mixed $payload): void {
	json_response(['ok' => true, 'data' => $payload]);
}

function respond_error(string $message, array $extra = [], int $status = 400): void {
	json_response(['ok' => false, 'error' => $message, 'extra' => $extra], $status);
}