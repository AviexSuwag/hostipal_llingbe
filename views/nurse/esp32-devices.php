<?php

require_once __DIR__ . '/../../controllers/NurseController.php';
require_once __DIR__ . '/../../api/esp32-devices.php';

$controller = new NurseController($pdo);

$departments = $pdo->query(
    'SELECT department_id, department_name, department_code
     FROM `department`
     WHERE is_active = 1
     ORDER BY department_name'
)->fetchAll();

$esp32Devices = [];
try {
    $esp32 = new Esp32Device($pdo);
    $esp32Devices = $esp32->list();
} catch (Throwable $e) {
    error_log('[views/nurse/esp32-devices.php] ' . $e->getMessage());
}

$pageScript = BASE_URL . '/assets/js/nurse-esp32-devices.js';
$pageScriptIsModule = true;

?>

<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">ESP32 Displays</h1>
        <p class="mt-1 text-sm text-slate-500">Manage ESP32-CYD display devices for nurse station notifications.</p>
    </div>
    <button type="button" id="openAddDevice"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Device
    </button>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Device Name</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">MAC Address</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Department</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Status</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Last Seen</th>
                    <th class="text-center px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($esp32Devices)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">No ESP32 devices registered yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($esp32Devices as $d):
                    $isActive = (int)$d['is_active'] === 1;
                    $lastSeen = $d['last_seen'] ? date('M j, Y g:i A', strtotime($d['last_seen'])) : 'Never';
                    $isOnline = $d['last_seen'] ? (time() - strtotime($d['last_seen']) < 300) : false;
                ?>
                    <tr class="hover:bg-slate-50 device-row" data-device-id="<?= (int)$d['device_id'] ?>">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars($d['device_name']) ?></p>
                            <p class="text-xs text-slate-500">ID #<?= (int)$d['device_id'] ?></p>
                        </td>
                        <td class="px-6 py-4 text-slate-600 font-mono text-xs">
                            <?= htmlspecialchars($d['mac_address']) ?>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <?= htmlspecialchars($d['department_name'] ?? 'Unknown') ?>
                            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($d['department_code'] ?? '') ?></p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                  style="background-color: <?= $isActive ? '#10b981' : '#ef4444' ?>1A;
                                         color: <?= $isActive ? '#10b981' : '#ef4444' ?>;
                                         border-color: <?= $isActive ? '#10b981' : '#ef4444' ?>40;">
                                <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $isActive ? '#10b981' : '#ef4444' ?>"></span>
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <?= htmlspecialchars($lastSeen) ?>
                            <?php if ($isOnline): ?>
                                <span class="ml-2 inline-flex items-center gap-1 text-[10px] text-emerald-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                    Online
                                </span>
                            <?php elseif ($d['last_seen']): ?>
                                <span class="ml-2 inline-flex items-center gap-1 text-[10px] text-amber-500">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Stale
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <button type="button" class="btn-toggle-active"
                                        data-device-id="<?= (int)$d['device_id'] ?>"
                                        data-is-active="<?= $isActive ? '1' : '0' ?>"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition
                                               <?= $isActive ? 'bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                                <button type="button" class="btn-regenerate-key"
                                        data-device-id="<?= (int)$d['device_id'] ?>"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-100 transition">
                                    New Key
                                </button>
                                <button type="button" class="btn-delete-device"
                                        data-device-id="<?= (int)$d['device_id'] ?>"
                                        data-device-name="<?= htmlspecialchars($d['device_name'], ENT_QUOTES) ?>"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 border border-rose-200 bg-rose-50 hover:bg-rose-100 transition">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="apiKeyModal" class="fixed inset-0 z-50 hidden" aria-labelledby="apiKeyModalTitle" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeApiKeyModal()"></div>
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-6 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6">
                    <h3 class="text-lg font-medium text-slate-900" id="apiKeyModalTitle">New API Key</h3>
                    <div class="mt-2">
                        <p class="text-sm text-slate-500">The API key below is used by the ESP32-CYD to authenticate with the hospital API. Copy it to the device configuration.</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                        <div class="relative">
                            <input type="text" id="apiKeyDisplay" readonly
                                   class="w-full rounded-lg border border-slate-300 pl-10 pr-10 py-2 text-sm font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-50">
                            <button type="button" onclick="copyApiKey()" class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Update the ESP32-CYD firmware with this key.</p>
                    </div>
                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeApiKeyModal()" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 transition">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="addDeviceModal" class="fixed inset-0 z-50 hidden" aria-labelledby="addDeviceModalTitle" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeAddDeviceModal()"></div>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-6 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6">
                <h3 class="text-lg font-medium text-slate-900" id="addDeviceModalTitle">Register ESP32 Device</h3>
                <form id="addDeviceForm" class="mt-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Device Name</label>
                        <input type="text" name="device_name" id="deviceNameInput" required
                               maxlength="100"
                               class="w-full rounded-lg border border-slate-300 pl-3 pr-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Nurse Station Display 1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MAC Address</label>
                        <input type="text" name="mac_address" id="macAddressInput" required
                               maxlength="17"
                               class="w-full rounded-lg border border-slate-300 pl-3 pr-3 py-2 text-sm font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="AA:BB:CC:DD:EE:FF">
                        <p class="mt-1 text-xs text-slate-500">Found on the ESP32 label or in the captive portal info page.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                        <select name="department_id" id="departmentSelect" required
                               class="w-full rounded-lg border border-slate-300 pl-3 pr-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int)$dept['department_id'] ?>">
                                    <?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" onclick="closeAddDeviceModal()" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 transition">Cancel</button>
                        <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="module">
    import { showSuccess, showError } from '<?= BASE_URL ?>/assets/js/sweetalert-notifications.js';

    const baseUrl = document.body.dataset.baseUrl;
    const apiPath = baseUrl + '/api/esp32-devices.php';

    async function apiCall(operation, method, body = null) {
        const params = new URLSearchParams({ operation });
        const url = apiPath + '?' + params.toString();
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Request failed.');
        }
        return data;
    }

    async function loadDevices() {
        try {
            const data = await apiCall('list', 'GET');
            renderDevices(data.data || []);
        } catch (err) {
            showError('Failed to load devices: ' + err.message);
        }
    }

    function renderDevices(devices) {
        const tbody = document.querySelector('.device-row').closest('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (devices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No ESP32 devices registered yet.</td></tr>';
            return;
        }

        for (const d of devices) {
            const isActive = d.is_active === 1 || d.is_active === '1';
            const lastSeen = d.last_seen ? new Date(d.last_seen).toLocaleString() : 'Never';
            const isOnline = d.last_seen ? (Date.now() - new Date(d.last_seen).getTime() < 300000) : false;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 device-row';
            tr.dataset.deviceId = d.device_id;
            tr.innerHTML = `
                <td class="px-6 py-4">
                    <p class="font-semibold text-slate-900">${escapeHtml(d.device_name)}</p>
                    <p class="text-xs text-slate-500">ID #${d.device_id}</p>
                </td>
                <td class="px-6 py-4 text-slate-600 font-mono text-xs">${escapeHtml(d.mac_address)}</td>
                <td class="px-6 py-4 text-slate-600">
                    ${escapeHtml(d.department_name || 'Unknown')}
                    <p class="text-xs text-slate-400 mt-1">${escapeHtml(d.department_code || '')}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                          style="background-color: ${isActive ? '#10b981' : '#ef4444'}1A;
                                 color: ${isActive ? '#10b981' : '#ef4444'};
                                 border-color: ${isActive ? '#10b981' : '#ef4444'}40;">
                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: ${isActive ? '#10b981' : '#ef4444'}"></span>
                        ${isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="px-6 py-4 text-slate-600">
                    ${escapeHtml(lastSeen)}
                    ${isOnline ? '<span class="ml-2 inline-flex items-center gap-1 text-[10px] text-emerald-600"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Online</span>' : ''}
                </td>
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                        <button type="button" class="btn-toggle-active rounded-lg px-3 py-1.5 text-xs font-medium transition ${isActive ? 'bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100'}" data-device-id="${d.device_id}" data-is-active="${isActive ? 1 : 0}">
                            ${isActive ? 'Deactivate' : 'Activate'}
                        </button>
                        <button type="button" class="btn-regenerate-key rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 border border-blue-200 bg-blue-50 hover:bg-blue-100 transition" data-device-id="${d.device_id}">
                            New Key
                        </button>
                        <button type="button" class="btn-delete-device rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 border border-rose-200 bg-rose-50 hover:bg-rose-100 transition" data-device-id="${d.device_id}" data-device-name="${escapeHtml(d.device_name)}">
                            Delete
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    document.getElementById('openAddDevice').addEventListener('click', () => {
        document.getElementById('addDeviceModal').classList.remove('hidden');
        document.getElementById('deviceNameInput').focus();
    });

    function closeAddDeviceModal() {
        document.getElementById('addDeviceModal').classList.add('hidden');
        document.getElementById('addDeviceForm').reset();
    }

    document.getElementById('addDeviceForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.textContent = 'Registering...';
        btn.disabled = true;

        try {
            const body = {
                device_name: document.getElementById('deviceNameInput').value.trim(),
                mac_address: document.getElementById('macAddressInput').value.trim().toUpperCase(),
                department_id: parseInt(document.getElementById('departmentSelect').value),
            };
            await apiCall('create', 'POST', body);
            showSuccess('ESP32 device registered.');
            closeAddDeviceModal();
            loadDevices();
        } catch (err) {
            showError(err.message);
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    });

    document.querySelector('tbody').addEventListener('click', async (e) => {
        const toggle = e.target.closest('.btn-toggle-active');
        const regen = e.target.closest('.btn-regenerate-key');
        const del = e.target.closest('.btn-delete-device');

        if (toggle) {
            const deviceId = toggle.dataset.deviceId;
            const isActive = toggle.dataset.isActive === '1';
            try {
                await apiCall('toggle-active', 'POST', { device_id: parseInt(deviceId) });
                toggle.dataset.isActive = isActive ? '0' : '1';
                toggle.textContent = isActive ? 'Activate' : 'Deactivate';
                toggle.className = isActive
                    ? 'rounded-lg px-3 py-1.5 text-xs font-medium bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition'
                    : 'rounded-lg px-3 py-1.5 text-xs font-medium bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 transition';
                showSuccess('Device ' + (isActive ? 'activated' : 'deactivated') + '.');
            } catch (err) {
                showError(err.message);
            }
        }

        if (regen) {
            const deviceId = regen.dataset.deviceId;
            try {
                const data = await apiCall('regenerate-key', 'POST', { device_id: parseInt(deviceId) });
                document.getElementById('apiKeyDisplay').value = data.data.api_key;
                document.getElementById('apiKeyModal').classList.remove('hidden');
            } catch (err) {
                showError(err.message);
            }
        }

        if (del) {
            const deviceId = del.dataset.deviceId;
            const deviceName = del.dataset.deviceName;
            const confirmed = await Swal.fire({
                title: 'Delete device?',
                text: 'Remove "' + deviceName + '"? This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
            });
            if (!confirmed.isConfirmed) return;
            try {
                await apiCall('delete', 'DELETE', { device_id: parseInt(deviceId) });
                showSuccess('Device deleted.');
                loadDevices();
            } catch (err) {
                showError(err.message);
            }
        }
    });

    window.copyApiKey = function () {
        const input = document.getElementById('apiKeyDisplay');
        input.select();
        document.execCommand('copy');
        showSuccess('API key copied to clipboard.');
    };

    window.closeApiKeyModal = function () {
        document.getElementById('apiKeyModal').classList.add('hidden');
    };

    loadDevices();
</script>
