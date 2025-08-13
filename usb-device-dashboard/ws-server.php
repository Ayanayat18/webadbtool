<?php

declare(strict_types=1);

// Minimal PHP WebSocket server (no external deps)
// Usage: php ws-server.php

require __DIR__ . '/includes/helpers.php';

$config = app_config();
$bindHost = $config['ws']['bind_host'] ?? '0.0.0.0';
$port = (int)($config['ws']['port'] ?? 8081);
$secret = (string)($config['ws']['secret'] ?? '');

set_time_limit(0);
error_reporting(E_ALL);

$context = stream_context_create([
	'ssl' => [
		'verify_peer' => false,
		'verify_peer_name' => false,
	]
]);

$server = @stream_socket_server("tcp://{$bindHost}:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (!$server) {
	fwrite(STDERR, "Failed to bind to {$bindHost}:{$port} - {$errstr} ({$errno})\n");
	exit(1);
}

fwrite(STDOUT, "WebSocket server listening on {$bindHost}:{$port}\n");

$clients = [];

while (true) {
	$read = [$server];
	foreach ($clients as $c) { $read[] = $c['conn']; }
	$write = $except = [];
	if (@stream_select($read, $write, $except, 1) === false) {
		continue;
	}
	foreach ($read as $sock) {
		if ($sock === $server) {
			$conn = @stream_socket_accept($server, 0);
			if ($conn) {
				stream_set_blocking($conn, true);
				$clients[(int)$conn] = ['conn' => $conn, 'handshaked' => false];
			}
			continue;
		}

		$id = (int)$sock;
		if (!isset($clients[$id])) { continue; }
		if (!$clients[$id]['handshaked']) {
			$headers = read_http_request($sock);
			if ($headers === null) { close_client($clients, $id); continue; }
			if (!isset($headers['sec-websocket-key'])) { close_client($clients, $id); continue; }
			if ($secret !== '' && (($headers['sec-websocket-protocol'] ?? '') !== $secret)) { close_client($clients, $id); continue; }
			$accept = base64_encode(sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
			$resp = "HTTP/1.1 101 Switching Protocols\r\n" .
				"Upgrade: websocket\r\n" .
				"Connection: Upgrade\r\n" .
				"Sec-WebSocket-Accept: {$accept}\r\n";
			if ($secret !== '') {
				$resp .= "Sec-WebSocket-Protocol: {$secret}\r\n";
			}
			$resp .= "\r\n";
			fwrite($sock, $resp);
			$clients[$id]['handshaked'] = true;
			continue;
		}

		$frame = ws_read_frame($sock);
		if ($frame === null) { close_client($clients, $id); continue; }
		if ($frame['opcode'] === 0x8) { close_client($clients, $id); continue; } // close
		if ($frame['opcode'] !== 0x1) { continue; } // text frames only
		$payload = $frame['payload'];
		$resp = handle_message($payload, $secret);
		ws_send_text($sock, $resp);
	}
}

function read_http_request($conn): ?array {
	$buffer = '';
	while (strpos($buffer, "\r\n\r\n") === false) {
		$chunk = fread($conn, 1024);
		if ($chunk === '' || $chunk === false) { return null; }
		$buffer .= $chunk;
		if (strlen($buffer) > 8192) { return null; }
	}
	$lines = explode("\r\n", $buffer);
	$headers = [];
	foreach ($lines as $i => $line) {
		if ($i === 0) { continue; }
		if ($line === '') { break; }
		[$k, $v] = array_map('trim', explode(':', $line, 2));
		$headers[strtolower($k)] = $v;
	}
	return $headers;
}

function ws_read_frame($conn): ?array {
	$hdr = fread($conn, 2);
	if ($hdr === '' || $hdr === false || strlen($hdr) < 2) { return null; }
	$b1 = ord($hdr[0]);
	$b2 = ord($hdr[1]);
	$fin = ($b1 & 0x80) !== 0;
	$opcode = $b1 & 0x0F;
	$masked = ($b2 & 0x80) !== 0;
	$len = ($b2 & 0x7F);
	if ($len === 126) {
		$ext = fread($conn, 2);
		if ($ext === false || strlen($ext) < 2) return null;
		$len = unpack('n', $ext)[1];
	} elseif ($len === 127) {
		$ext = fread($conn, 8);
		if ($ext === false || strlen($ext) < 8) return null;
		$parts = unpack('J', $ext);
		$len = $parts ? $parts[1] : 0;
	}
	$maskKey = '';
	if ($masked) {
		$maskKey = fread($conn, 4);
		if ($maskKey === false || strlen($maskKey) < 4) return null;
	}
	$payload = '';
	$remaining = $len;
	while ($remaining > 0) {
		$chunk = fread($conn, $remaining);
		if ($chunk === false || $chunk === '') return null;
		$payload .= $chunk;
		$remaining -= strlen($chunk);
	}
	if ($masked) {
		$payload = ws_apply_mask($payload, $maskKey);
	}
	return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
}

function ws_apply_mask(string $data, string $mask): string {
	$out = '';
	$ml = strlen($mask);
	$dl = strlen($data);
	for ($i = 0; $i < $dl; $i++) {
		$out .= $data[$i] ^ $mask[$i % $ml];
	}
	return $out;
}

function ws_send_text($conn, string $payload): void {
	$frame = chr(0x81);
	$len = strlen($payload);
	if ($len <= 125) {
		$frame .= chr($len);
	} elseif ($len <= 65535) {
		$frame .= chr(126) . pack('n', $len);
	} else {
		$frame .= chr(127) . pack('J', $len);
	}
	fwrite($conn, $frame . $payload);
}

function close_client(array &$clients, int $id): void {
	if (isset($clients[$id])) {
		@fclose($clients[$id]['conn']);
		unset($clients[$id]);
	}
}

function handle_message(string $payload, string $secret): string {
	try {
		$req = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
		$action = (string)($req['action'] ?? '');
		$tok = (string)($req['token'] ?? '');
		if ($secret !== '' && $tok !== $secret) {
			return json_encode(['ok' => false, 'error' => 'unauthorized']);
		}
		switch ($action) {
			case 'ping':
				return json_encode(['ok' => true, 'action' => 'pong', 'ts' => time()]);
			case 'fastboot.auto':
				$devices = detect_fastboot_device();
				if (count($devices) > 0) {
					$serial = $devices[0]['serial'];
					$vars = fastboot_getvars($serial);
					return json_encode(['ok' => true, 'action' => $action, 'data' => ['serial' => $serial, 'vars' => $vars, 'summary' => extract_fastboot_summary($vars)]]);
				}
				return json_encode(['ok' => true, 'action' => $action, 'data' => ['serial' => null, 'vars' => [], 'summary' => []]]);
			case 'fastboot.getvars':
				$serial = (string)($req['serial'] ?? '');
				$vars = fastboot_getvars($serial ?: null);
				return json_encode(['ok' => true, 'action' => $action, 'data' => ['serial' => $serial ?: null, 'vars' => $vars, 'summary' => extract_fastboot_summary($vars)]]);
			case 'adb.devices':
				return json_encode(['ok' => true, 'action' => $action, 'data' => adb_list_devices()]);
			case 'adb.props':
				$id = (string)($req['id'] ?? '');
				if ($id === '') { return json_encode(['ok' => false, 'error' => 'missing id']); }
				$props = adb_get_properties($id);
				return json_encode(['ok' => true, 'action' => $action, 'data' => ['id' => $id, 'props' => $props, 'summary' => extract_adb_summary($props)]]);
			case 'mtp.detect':
				return json_encode(['ok' => true, 'action' => $action, 'data' => mtp_detect_info()]);
			case 'samsung.ports':
				return json_encode(['ok' => true, 'action' => $action, 'data' => detect_samsung_com_ports()]);
			case 'samsung.probe':
				$port = $req['port'] ?? null;
				$data = heimdall_print_pit($port ? (string)$port : null);
				return json_encode(['ok' => true, 'action' => $action, 'data' => ['port' => $port, 'output' => $data]]);
			default:
				return json_encode(['ok' => false, 'error' => 'unknown action']);
		}
	} catch (Throwable $e) {
		return json_encode(['ok' => false, 'error' => 'bad_request', 'detail' => $e->getMessage()]);
	}
}