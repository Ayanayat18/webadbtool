<?php

declare(strict_types=1);

// Basic app settings
return [
	'app' => [
		'name' => 'USB Device Dashboard',
		'base_url' => '', // Auto-detected; can be left empty
		'enable_debug' => true,
		'default_timeout_seconds' => 12,
	],
	'ws' => [
		'enabled' => true,
		'bind_host' => '0.0.0.0',
		'port' => 8081,
		'public_url' => null, // If null, will use ws://<current-host>:<port>
		'secret' => '', // Optional shared secret
	],
	'paths' => (function () {
		$osFamily = PHP_OS_FAMILY; // 'Windows', 'Linux', 'Darwin', etc.
		$rootDir = __DIR__;
		$binBaseDir = $rootDir . DIRECTORY_SEPARATOR . 'bin';

		$isWindows = (stripos($osFamily, 'Windows') !== false || DIRECTORY_SEPARATOR === '\\');
		$binDir = $binBaseDir . DIRECTORY_SEPARATOR . ($isWindows ? 'windows' : 'linux');

		if ($isWindows) {
			$adbCmd = $binDir . DIRECTORY_SEPARATOR . 'adb.cmd';
			$adbExe = $binDir . DIRECTORY_SEPARATOR . 'adb.exe';
			$fastbootCmd = $binDir . DIRECTORY_SEPARATOR . 'fastboot.cmd';
			$fastbootExe = $binDir . DIRECTORY_SEPARATOR . 'fastboot.exe';
			$heimdallCmd = $binDir . DIRECTORY_SEPARATOR . 'heimdall.cmd';
			$heimdallExe = $binDir . DIRECTORY_SEPARATOR . 'heimdall.exe';
			$mtpCmd = $binDir . DIRECTORY_SEPARATOR . 'mtp-detect.cmd';
			$mtpExe = $binDir . DIRECTORY_SEPARATOR . 'mtp-detect.exe';

			$adb = file_exists($adbCmd) ? $adbCmd : $adbExe;
			$fastboot = file_exists($fastbootCmd) ? $fastbootCmd : $fastbootExe;
			$heimdall = file_exists($heimdallCmd) ? $heimdallCmd : $heimdallExe;
			$mtpDetect = file_exists($mtpCmd) ? $mtpCmd : $mtpExe;
		} else {
			$adb = $binDir . DIRECTORY_SEPARATOR . 'adb';
			$fastboot = $binDir . DIRECTORY_SEPARATOR . 'fastboot';
			$heimdall = $binDir . DIRECTORY_SEPARATOR . 'heimdall';
			$mtpDetect = $binDir . DIRECTORY_SEPARATOR . 'mtp-detect';
		}

		return [
			'root_dir' => $rootDir,
			'bin_dir' => $binDir,
			'adb' => $adb,
			'fastboot' => $fastboot,
			'heimdall' => $heimdall,
			'mtp_detect' => $mtpDetect,
		];
	})(),
];