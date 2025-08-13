<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? 'auto';

switch ($action) {
	case 'devices':
		respond_ok(adb_list_devices());
		break;
	case 'props':
		$deviceId = $_GET['id'] ?? '';
		if ($deviceId === '') {
			respond_error('Missing id');
		}
		$props = adb_get_properties($deviceId);
		respond_ok(['id' => $deviceId, 'props' => $props, 'summary' => extract_adb_summary($props)]);
		break;
	case 'auto':
	default:
		$devices = adb_list_devices();
		if (count($devices) > 0) {
			$id = $devices[0]['id'];
			$props = adb_get_properties($id);
			respond_ok(['id' => $id, 'props' => $props, 'summary' => extract_adb_summary($props)]);
		} else {
			respond_ok(['id' => null, 'props' => [], 'summary' => []]);
		}
}