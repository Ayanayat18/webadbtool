<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function asset_url(string $path): string {
	return './' . ltrim($path, '/');
}

?><!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($config['app']['name']); ?></title>
	<link rel="stylesheet" href="<?php echo asset_url('assets/bootstrap/css/bootstrap.min.css'); ?>">
	<link rel="stylesheet" href="<?php echo asset_url('assets/css/app.css'); ?>">
</head>
<body>
	<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
		<div class="container-fluid">
			<a class="navbar-brand" href="#"><?php echo htmlspecialchars($config['app']['name']); ?></a>
			<div class="d-flex align-items-center gap-2">
				<button id="themeToggle" class="btn btn-outline-secondary btn-sm" type="button" title="Toggle theme">🌙</button>
			</div>
		</div>
	</nav>

	<div class="container py-3">
		<ul class="nav nav-tabs" id="modeTabs" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link active" id="fastboot-tab" data-bs-toggle="tab" data-bs-target="#fastboot" type="button" role="tab" aria-controls="fastboot" aria-selected="true">Fastboot</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="adb-tab" data-bs-toggle="tab" data-bs-target="#adb" type="button" role="tab" aria-controls="adb" aria-selected="false">ADB / MTP</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="samsung-tab" data-bs-toggle="tab" data-bs-target="#samsung" type="button" role="tab" aria-controls="samsung" aria-selected="false">Samsung COM Port</button>
			</li>
		</ul>
		<div class="tab-content p-3 border border-top-0 rounded-bottom" id="modeTabsContent">
			<div class="tab-pane fade show active" id="fastboot" role="tabpanel" aria-labelledby="fastboot-tab" tabindex="0">
				<div class="d-flex gap-2 mb-2">
					<button class="btn btn-primary btn-sm" id="fastbootRefresh">Auto-detect</button>
					<div class="input-group input-group-sm" style="max-width: 380px;">
						<span class="input-group-text">Manual serial</span>
						<input type="text" class="form-control" id="fastbootSerial" placeholder="Serial">
						<button class="btn btn-outline-secondary" id="fastbootGetvars">Getvars</button>
					</div>
					<button class="btn btn-outline-secondary btn-sm d-none" id="fastbootWebUsb">Select via WebUSB</button>
				</div>
				<div id="fastbootSummary"></div>
				<pre class="small" id="fastbootRaw"></pre>
			</div>

			<div class="tab-pane fade" id="adb" role="tabpanel" aria-labelledby="adb-tab" tabindex="0">
				<div class="d-flex flex-wrap gap-2 mb-2">
					<button class="btn btn-primary btn-sm" id="adbAuto">Auto-detect</button>
					<button class="btn btn-outline-secondary btn-sm" id="adbList">Select ADB Device</button>
					<button class="btn btn-outline-secondary btn-sm" id="mtpDetect">MTP Detect</button>
					<button class="btn btn-outline-secondary btn-sm d-none" id="adbWebUsb">Select via WebUSB</button>
					<button class="btn btn-success btn-sm d-none" id="adbWebAdb">Connect WebADB</button>
				</div>
				<div id="adbDevices" class="row gy-2"></div>
				<div id="adbSummary"></div>
				<pre class="small" id="adbRaw"></pre>
			</div>

			<div class="tab-pane fade" id="samsung" role="tabpanel" aria-labelledby="samsung-tab" tabindex="0">
				<div class="d-flex flex-wrap gap-2 mb-2">
					<button class="btn btn-primary btn-sm" id="samsungAuto">Auto-detect</button>
					<button class="btn btn-outline-secondary btn-sm" id="samsungList">Select COM Port</button>
				</div>
				<div id="samsungPorts" class="row gy-2"></div>
				<pre class="small" id="samsungRaw"></pre>
			</div>
		</div>
	</div>

	<script src="<?php echo asset_url('assets/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
	<script src="<?php echo asset_url('assets/js/app.js'); ?>"></script>
</body>
</html>