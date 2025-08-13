# USB Device Dashboard

Plug-and-play PHP 8+ web app (Bootstrap 5) to read and display information from Android devices connected via Fastboot, ADB/MTP, or Samsung Download/COM Port.

## Features
- Fastboot: auto-detect, manual serial, `getvar all` parsing
- ADB/MTP: list devices, select to show `getprop`, run `mtp-detect`
- Samsung: list COM ports, probe with `heimdall print-pit`
- Bootstrap 5 UI with light/dark toggle, responsive layout, AJAX refresh
- Optional WebSocket server for instant actions and selection
- Full in-browser ADB via WebUSB (Chromium-based browsers) — no server binaries required for ADB
- No database, no installer

## Quick Start
1. Upload this folder to your hosting (cPanel) or local PHP server.
2. Ensure PHP 8+ with `proc_open`/`exec` enabled.
3. Linux: place platform-tools binaries as:
   - `bin/linux/adb.bin`, `bin/linux/fastboot.bin`
   - `bin/linux/heimdall.bin`, `bin/linux/mtp-detect.bin`
   Or install them in PATH. Wrappers in `bin/linux/*` will find them.
4. Windows: put binaries next to wrappers in `bin/windows/` (e.g., `adb.exe`, `fastboot.exe`, `heimdall.exe`, `mtp-detect.exe`) or ensure they’re in PATH.
5. Optional: start WebSocket server for live actions
   - Linux: `bin/linux/ws-server`
   - Windows: `bin/windows/ws-server.cmd`
   - Defaults to `ws://0.0.0.0:8081`. Configure in `config.php` under `ws`.
6. Visit `index.php` in a Chromium-based browser for WebUSB support.

## WebUSB / WebADB
- Click “Connect WebADB” to run ADB entirely in browser via WebUSB.
- Requires Chrome/Edge on HTTPS or localhost, USB debugging enabled, and driver/udev set up.
- “Select via WebUSB” can still be used for basic serial detection.

## Notes
- Some shared hosts restrict USB access and process execution. For full functionality use a local machine or a VPS with USB passthrough.
- Samsung COM detection on Linux scans `/dev/ttyUSB*` and `/dev/ttyACM*`. On Windows it uses the `mode` command.
- MTP detection uses `mtp-detect` (Linux). If unavailable, the UI will show raw output as empty.

## Structure
- `assets/` CSS, JS, Bootstrap
- `includes/` PHP helpers
- `api/` endpoints: `fastboot.php`, `adb.php`, `samsung.php`, `mtp.php`
- `bin/` wrappers for Linux/Windows incl. `ws-server`
- `ws-server.php` minimal WebSocket server
- `index.php` UI dashboard
- `config.php` paths and settings

## Security
- This app executes system commands. Host it on trusted machines only.
- WebSocket server is unauthenticated by default. Set `ws.secret` and pass via subprotocol if you expose it.