(function () {
	'use strict';

	function $(sel) { return document.querySelector(sel); }
	function $all(sel) { return Array.from(document.querySelectorAll(sel)); }

	function renderTable(container, obj) {
		const entries = Object.entries(obj || {}).filter(([, v]) => String(v).trim() !== '');
		if (entries.length === 0) { container.innerHTML = '<div class="text-muted">No data.</div>'; return; }
		let html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">';
		html += '<tbody>';
		for (const [k, v] of entries) {
			html += `<tr><th class="text-nowrap" style="width: 220px;">${escapeHtml(k)}</th><td>${escapeHtml(String(v))}</td></tr>`;
		}
		html += '</tbody></table></div>';
		container.innerHTML = html;
	}

	function escapeHtml(s) {
		return s.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
	}

	function get(url) {
		return fetch(url, {cache: 'no-store'}).then(r => r.json());
	}

	// Theme toggle
	(function initTheme() {
		const btn = $('#themeToggle');
		const root = document.documentElement;
		const saved = localStorage.getItem('theme');
		if (saved) root.setAttribute('data-bs-theme', saved);
		btn?.addEventListener('click', () => {
			const current = root.getAttribute('data-bs-theme') || 'light';
			const next = current === 'light' ? 'dark' : 'light';
			root.setAttribute('data-bs-theme', next);
			localStorage.setItem('theme', next);
		});
	})();

	// WebUSB detection and handlers
	function supportsWebUsb() { return !!(navigator.usb && navigator.usb.requestDevice); }
	function showWebUsbButtonsIfSupported() {
		if (!supportsWebUsb()) return;
		$('#fastbootWebUsb')?.classList.remove('d-none');
		$('#adbWebUsb')?.classList.remove('d-none');
	}
	showWebUsbButtonsIfSupported();

	function hex4(n) { return ('0000' + Number(n).toString(16)).slice(-4); }

	async function selectViaWebUsb(context) {
		try {
			if (!supportsWebUsb()) return;
			const filters = [
				{ classCode: 0xFF, subclassCode: 0x42 }, // ADB/Fastboot interface
				{ classCode: 0xFF }, // any vendor-specific interface
				{ vendorId: 0x18D1 }, // Google
				{ vendorId: 0x04E8 }, // Samsung
				{ vendorId: 0x2717 }, // Xiaomi
				{ vendorId: 0x2A70 }, // OnePlus
				{ vendorId: 0x22B8 }, // Motorola
				{ vendorId: 0x12D1 }, // Huawei
				{ vendorId: 0x0FCE }, // Sony Mobile
				{ vendorId: 0x1004 }, // LG
				{ vendorId: 0x22D9 }, // OPPO/Realme
				{ vendorId: 0x2D95 }, // vivo
			];
			const device = await navigator.usb.requestDevice({ filters });
			try { await device.open(); } catch (_) {}
			const info = {
				manufacturer: device.manufacturerName || '',
				product: device.productName || '',
				serial: device.serialNumber || '',
				vendorId: device.vendorId,
				productId: device.productId,
			};
			if (context === 'fastboot') {
				if (info.serial) { $('#fastbootSerial').value = info.serial; }
				renderTable($('#fastbootSummary'), {
					'Manufacturer (WebUSB)': info.manufacturer,
					'Product (WebUSB)': info.product,
					'Serial (WebUSB)': info.serial || '(unknown)',
					'USB VID:PID': `${hex4(info.vendorId)}:${hex4(info.productId)}`,
				});
				refreshFastbootGetvars();
			} else if (context === 'adb') {
				const serial = info.serial;
				const afterList = (list) => {
					const devs = Array.isArray(list) ? list : [];
					const match = devs.find(d => d.id === serial);
					if (match) {
						adbProps(match.id);
					} else {
						renderTable($('#adbSummary'), {
							'Manufacturer (WebUSB)': info.manufacturer,
							'Product (WebUSB)': info.product,
							'Serial (WebUSB)': serial || '(unknown)',
							'USB VID:PID': `${hex4(info.vendorId)}:${hex4(info.productId)}`,
							'Note': 'No matching ADB device id found. Make sure ADB is enabled and authorized.'
						});
					}
				};
				get('api/adb.php?action=devices').then(j => afterList(j.data || [])).catch(() => afterList([]));
			}
		} catch (e) {
			console.warn('WebUSB selection failed', e);
		}
	}

	$('#fastbootWebUsb')?.addEventListener('click', () => selectViaWebUsb('fastboot'));
	$('#adbWebUsb')?.addEventListener('click', () => selectViaWebUsb('adb'));

	// WebSocket client
	let ws = null;
	let wsReady = false;
	function wsUrl() {
		const port = 8081;
		const host = location.hostname;
		const proto = location.protocol === 'https:' ? 'wss' : 'ws';
		return `${proto}://${host}:${port}`;
	}
	function wsSend(msg) {
		if (ws && wsReady) { ws.send(JSON.stringify(msg)); return true; }
		return false;
	}
	function wsConnect() {
		try {
			ws = new WebSocket(wsUrl());
			ws.addEventListener('open', () => { wsReady = true; });
			ws.addEventListener('close', () => { wsReady = false; });
			ws.addEventListener('error', () => { wsReady = false; });
			ws.addEventListener('message', (ev) => {
				try {
					const j = JSON.parse(ev.data);
					if (!j || j.ok === false) return;
					const a = j.action;
					const d = j.data || {};
					if (a === 'fastboot.auto' || a === 'fastboot.getvars') {
						renderTable($('#fastbootSummary'), d.summary || {});
						$('#fastbootRaw').textContent = JSON.stringify(d.vars || {}, null, 2);
						if (d.serial) { $('#fastbootSerial').value = d.serial; }
					}
					if (a === 'adb.devices') {
						renderAdbDevices(d);
					}
					if (a === 'adb.props') {
						renderTable($('#adbSummary'), d.summary || {});
						$('#adbRaw').textContent = JSON.stringify(d.props || {}, null, 2);
					}
					if (a === 'mtp.detect') {
						$('#adbRaw').textContent = typeof d.raw === 'string' ? d.raw : JSON.stringify(d, null, 2);
					}
					if (a === 'samsung.ports') {
						renderSamsungPorts(d);
					}
					if (a === 'samsung.probe') {
						$('#samsungRaw').textContent = (d.output?.stdout || '') + '\n' + (d.output?.stderr || '');
					}
				} catch {}
			});
		} catch {}
	}
	wsConnect();

	// Fastboot
	function refreshFastbootAuto() {
		if (wsSend({action: 'fastboot.auto'})) return;
		get('api/fastboot.php?action=auto').then(j => {
			if (!j.ok) { $('#fastbootSummary').innerHTML = '<div class="text-danger">Error</div>'; return; }
			renderTable($('#fastbootSummary'), j.data.summary || {});
			$('#fastbootRaw').textContent = JSON.stringify(j.data.vars || {}, null, 2);
			if (j.data.serial) { $('#fastbootSerial').value = j.data.serial; }
		});
	}
	function refreshFastbootGetvars() {
		const serial = $('#fastbootSerial').value.trim();
		if (wsSend({action: 'fastboot.getvars', serial})) return;
		const qs = serial ? `&serial=${encodeURIComponent(serial)}` : '';
		get('api/fastboot.php?action=getvars' + qs).then(j => {
			renderTable($('#fastbootSummary'), j.data.summary || {});
			$('#fastbootRaw').textContent = JSON.stringify(j.data.vars || {}, null, 2);
		});
	}
	$('#fastbootRefresh')?.addEventListener('click', refreshFastbootAuto);
	$('#fastbootGetvars')?.addEventListener('click', refreshFastbootGetvars);

	// ADB
	function adbAuto() {
		if (wsSend({action: 'adb.devices'})) return;
		get('api/adb.php?action=auto').then(j => {
			renderTable($('#adbSummary'), j.data.summary || {});
			$('#adbRaw').textContent = JSON.stringify(j.data.props || {}, null, 2);
		});
	}
	function renderAdbDevices(list) {
		const container = $('#adbDevices');
		if (!Array.isArray(list) || list.length === 0) { container.innerHTML = '<div class="text-muted">No ADB devices.</div>'; return; }
		container.innerHTML = list.map(d => `
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card card-body p-2">
					<div class="d-flex justify-content-between align-items-center">
						<div>
							<div class="fw-semibold">${escapeHtml(d.id)}</div>
							<div class="text-muted small">${escapeHtml(d.status)}</div>
						</div>
						<button class="btn btn-sm btn-outline-primary" data-adb-id="${escapeHtml(d.id)}">Select</button>
					</div>
				</div>
			</div>
		`).join('');
		$all('[data-adb-id]').forEach(btn => btn.addEventListener('click', () => adbProps(btn.getAttribute('data-adb-id'))));
	}
	function adbList() {
		if (wsSend({action: 'adb.devices'})) return;
		get('api/adb.php?action=devices').then(j => renderAdbDevices(j.data || []));
	}
	function adbProps(id) {
		if (wsSend({action: 'adb.props', id})) return;
		get('api/adb.php?action=props&id=' + encodeURIComponent(id)).then(j => {
			renderTable($('#adbSummary'), j.data.summary || {});
			$('#adbRaw').textContent = JSON.stringify(j.data.props || {}, null, 2);
		});
	}
	$('#adbAuto')?.addEventListener('click', adbAuto);
	$('#adbList')?.addEventListener('click', adbList);

	// MTP
	function mtp() {
		if (wsSend({action: 'mtp.detect'})) return;
		get('api/mtp.php?action=detect').then(j => {
			$('#adbRaw').textContent = typeof j.data.raw === 'string' ? j.data.raw : JSON.stringify(j.data, null, 2);
		});
	}
	$('#mtpDetect')?.addEventListener('click', mtp);

	// Samsung
	function samsungAuto() {
		if (wsSend({action: 'samsung.probe'})) return;
		get('api/samsung.php?action=auto').then(j => {
			$('#samsungRaw').textContent = (j.data.output?.stdout || '') + '\n' + (j.data.output?.stderr || '');
		});
	}
	function renderSamsungPorts(ports) {
		const container = $('#samsungPorts');
		if (!Array.isArray(ports) || ports.length === 0) { container.innerHTML = '<div class="text-muted">No ports detected.</div>'; return; }
		container.innerHTML = ports.map(p => `
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card card-body p-2 d-flex justify-content-between align-items-center flex-row">
					<div class="fw-semibold">${escapeHtml(p.port)}</div>
					<button class="btn btn-sm btn-outline-primary" data-port="${escapeHtml(p.port)}">Probe</button>
				</div>
			</div>
		`).join('');
		$all('[data-port]').forEach(btn => btn.addEventListener('click', () => samsungProbe(btn.getAttribute('data-port'))));
	}
	function samsungList() {
		if (wsSend({action: 'samsung.ports'})) return;
		get('api/samsung.php?action=ports').then(j => renderSamsungPorts(j.data || []));
	}
	function samsungProbe(port) {
		if (wsSend({action: 'samsung.probe', port})) return;
		get('api/samsung.php?action=probe&port=' + encodeURIComponent(port)).then(j => {
			$('#samsungRaw').textContent = (j.data.output?.stdout || '') + '\n' + (j.data.output?.stderr || '');
		});
	}
	$('#samsungAuto')?.addEventListener('click', samsungAuto);
	$('#samsungList')?.addEventListener('click', samsungList);

	// Auto load
	refreshFastbootAuto();
	adbAuto();
	// samsungAuto(); // do not auto run heimdall unless requested
})();