// This is an ES module. It loads WebADB (yume-chan) from a CDN and exposes a minimal API.
// It fails gracefully if the library or browser support is missing.

const state = {
	adb: null,
	transport: null,
	device: null,
};

async function loadLibs() {
	const [{ Adb, AdbDaemonTransport }, { AdbDaemonWebUsbDeviceManager }] = await Promise.all([
		import('https://esm.sh/@yume-chan/adb@0.11.0?bundle'),
		import('https://esm.sh/@yume-chan/adb-backend-webusb@0.11.0?bundle'),
	]);
	return { Adb, AdbDaemonTransport, AdbDaemonWebUsbDeviceManager };
}

async function connect() {
	if (!('usb' in navigator)) throw new Error('WebUSB not supported');
	const { Adb, AdbDaemonTransport, AdbDaemonWebUsbDeviceManager } = await loadLibs();
	const manager = new AdbDaemonWebUsbDeviceManager(navigator.usb);
	const device = await manager.requestDevice();
	const connection = await device.connect();
	const transport = await AdbDaemonTransport.authenticate({
		connection,
		serial: device.serial,
		// For simplicity, use no auth; device will prompt to authorize
	});
	const adb = new Adb(transport);
	state.device = device;
	state.transport = transport;
	state.adb = adb;
	return { serial: device.serial ?? '', product: device.productName ?? '' };
}

async function disconnect() {
	try { await state.transport?.dispose(); } catch {}
	try { await state.device?.close?.(); } catch {}
	state.adb = null;
	state.transport = null;
	state.device = null;
}

async function shell(command) {
	if (!state.adb) throw new Error('Not connected');
	const stream = await state.adb.subprocess.shell(command);
	const text = await stream.stdout.readToEnd();
	await stream.close();
	return new TextDecoder().decode(text);
}

async function getpropAll() {
	const out = await shell('getprop');
	return out;
}

window.WebAdb = { connect, disconnect, shell, getpropAll };