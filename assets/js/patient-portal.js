(function () {
    const baseUrl = document.body.dataset.baseUrl;
    const apiUrl = `${baseUrl}/api/patient-portal.php`;
    const loginSection = document.getElementById('patientPortalLogin');
    const recordsSection = document.getElementById('patientPortalRecords');
    const loginForm = document.getElementById('patientPortalLoginForm');
    const loginError = document.getElementById('portalLoginError');
    const loginButton = document.getElementById('patientPortalLoginButton');
    const captchaQuestion = document.getElementById('captchaQuestion');
    const captchaAnswer = document.getElementById('captchaAnswer');
    const loading = document.getElementById('patientPortalLoading');
    const content = document.getElementById('patientPortalContent');
    let idleTimer;
    let lastActivity = Date.now();
    let captchaReady = false;
    let recordsRefreshInProgress = false;
    let recordsRefreshRequested = false;
    let patientSocket = null;
    let patientSocketReconnectTimer = null;
    let patientRecordsRefreshTimer = null;
    let patientId = 0;
    let admissionIds = new Set();
    let statementIds = new Set();
    let websocketStatus = 'Connecting';

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
    const money = value => `₱${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const date = value => {
        if (!value) return '—';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-PH', {
            year: 'numeric', month: 'short', day: 'numeric',
        });
    };

    async function request(operation, { method = 'GET', body } = {}) {
        const url = new URL(apiUrl, location.origin);
        url.searchParams.set('operation', operation);
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: body ? { 'Content-Type': 'application/json', Accept: 'application/json' } : { Accept: 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            const error = new Error(result.message || 'The request could not be completed.');
            error.status = response.status;
            throw error;
        }
        return result;
    }

    async function refreshCaptcha() {
        captchaReady = false;
        loginButton.disabled = true;
        try {
            const result = await request('captcha');
            captchaQuestion.textContent = result.question;
            captchaAnswer.value = '';
            captchaReady = true;
        } finally {
            loginButton.disabled = !captchaReady;
        }
    }

    function showLoginError(message) {
        loginError.textContent = message;
        loginError.classList.remove('hidden');
    }

    function updateWebsocketStatus(status) {
        websocketStatus = status;
        const statusElement = document.getElementById('patientPortalWebsocketStatus');
        if (statusElement) {
            statusElement.textContent = `Live updates: ${status}`;
            statusElement.classList.toggle('text-emerald-700', status === 'Connected');
            statusElement.classList.toggle('text-amber-700', status !== 'Connected');
        }
    }

    function closePatientSocket() {
        clearTimeout(patientSocketReconnectTimer);
        patientSocketReconnectTimer = null;
        if (patientSocket) {
            patientSocket.onclose = null;
            patientSocket.close();
            patientSocket = null;
        }
        updateWebsocketStatus('Disconnected');
    }

    function schedulePatientSocketReconnect() {
        if (patientSocketReconnectTimer || document.body.dataset.authenticated !== '1') return;
        patientSocketReconnectTimer = setTimeout(() => {
            patientSocketReconnectTimer = null;
            connectPatientSocket();
        }, 3000);
    }

    function subscribeToPatientUpdates() {
        if (patientSocket?.readyState !== WebSocket.OPEN || patientId <= 0) return;
        try {
            patientSocket.send(JSON.stringify({
                action: 'subscribe_patient_portal',
                patient_id: patientId,
                admission_ids: Array.from(admissionIds),
                statement_ids: Array.from(statementIds),
            }));
        } catch (error) {
            console.error('[patient-portal] Could not subscribe to live record updates.', error);
        }
    }

    function connectPatientSocket() {
        if (document.body.dataset.authenticated !== '1'
            || patientSocket?.readyState === WebSocket.CONNECTING
            || patientSocket?.readyState === WebSocket.OPEN) {
            return;
        }

        const socketUrl = new URL(location.href);
        socketUrl.protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        socketUrl.port = String(window.HOSPITAL_WEBSOCKET_PORT || 8090);
        socketUrl.pathname = '/';
        socketUrl.search = '';
        socketUrl.searchParams.set('channel', 'patient-portal');
        socketUrl.hash = '';
        updateWebsocketStatus('Connecting');

        try {
            patientSocket = new WebSocket(socketUrl.href);
        } catch (error) {
            console.error('[patient-portal] Could not create WebSocket connection.', error);
            updateWebsocketStatus('Offline');
            schedulePatientSocketReconnect();
            return;
        }

        patientSocket.onopen = () => {
            updateWebsocketStatus('Connected');
            subscribeToPatientUpdates();
        };
        patientSocket.onmessage = event => {
            let message;
            try {
                message = JSON.parse(event.data);
            } catch (error) {
                console.warn('[patient-portal] Ignoring malformed WebSocket message.', error);
                return;
            }

            if (message.type !== 'portal.refresh') return;
            clearTimeout(patientRecordsRefreshTimer);
            patientRecordsRefreshTimer = setTimeout(() => {
                patientRecordsRefreshTimer = null;
                loadPatientRecords();
            }, 250);
        };
        patientSocket.onerror = () => {
            updateWebsocketStatus('Connection error');
            patientSocket?.close();
        };
        patientSocket.onclose = () => {
            patientSocket = null;
            updateWebsocketStatus('Offline — reconnecting');
            schedulePatientSocketReconnect();
        };
    }

    function renderPatientRecords(data) {
        const patient = data.patient;
        patientId = Number(patient.patient_id || 0);
        admissionIds = new Set(data.admissions.map(admission => Number(admission.admission_id)).filter(Boolean));
        statementIds = new Set(data.statements.map(statement => Number(statement.statement_id)).filter(Boolean));
        subscribeToPatientUpdates();
        const statementRows = data.statements.length
            ? data.statements.map(statement => `
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-3">#${Number(statement.statement_id)} · Admission #${Number(statement.admission_id)}</td>
                    <td class="px-4 py-3">${escapeHtml(date(statement.statement_date))}</td>
                    <td class="px-4 py-3">${escapeHtml(statement.status)}</td>
                    <td class="px-4 py-3 text-right">${money(statement.total_amount)}</td>
                    <td class="px-4 py-3 text-right">${money(statement.amount_paid)}</td>
                    <td class="px-4 py-3 text-right font-semibold">${money(statement.balance_amount)}</td>
                    <td class="px-4 py-3 text-right"><a target="_blank" rel="noopener" href="${baseUrl}/patient-statement.php?statement_id=${Number(statement.statement_id)}" class="whitespace-nowrap rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">View Statement</a></td>
                </tr>`).join('')
            : '<tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No billing statements available.</td></tr>';
        const statementCards = data.statements.length
            ? data.statements.map(statement => {
                const grouped = (statement.charges || []).reduce((result, charge) => {
                    const category = charge.category_name || 'Other charges';
                    (result[category] ||= []).push(charge);
                    return result;
                }, {});
                const chargeGroups = Object.entries(grouped).map(([category, charges]) => `
                    <div class="border-t border-slate-100 py-3 first:border-0">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(category)}</h4>
                        <ul class="mt-2 space-y-2">${charges.map(charge => `
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <span class="min-w-0 text-slate-700">${charge.source_type === 'MEDICINE_RETURN' ? 'Returned to Pharmacy — ' : ''}${escapeHtml(charge.item_name)} <span class="text-xs text-slate-500">× ${charge.source_type === 'MEDICINE_RETURN' ? `${Math.abs(Number(charge.quantity))} returned` : Number(charge.quantity)}</span></span>
                                <span class="shrink-0 text-right font-medium text-slate-800">${money(charge.line_total)}</span>
                            </li>`).join('')}</ul>
                    </div>`).join('');
                return `<article class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">Statement #${Number(statement.statement_id)}</h3>
                            <p class="mt-1 text-xs text-slate-500">Admission #${Number(statement.admission_id)} · ${escapeHtml(date(statement.statement_date))}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">${escapeHtml(statement.status)}</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                        <div><dt class="text-xs text-slate-500">Total</dt><dd class="mt-0.5 text-sm font-medium text-slate-900">${money(statement.total_amount)}</dd></div>
                        <div><dt class="text-xs text-slate-500">Paid</dt><dd class="mt-0.5 text-sm font-medium text-slate-900">${money(statement.amount_paid)}</dd></div>
                        <div><dt class="text-xs text-slate-500">${statement.id_benefit_eligible ? 'ID-evidence discount (20%)' : 'Discount'}</dt><dd class="mt-0.5 text-sm font-medium text-slate-900">− ${money(statement.applied_discount)}</dd></div>
                        <div><dt class="text-xs text-slate-500">VAT</dt><dd class="mt-0.5 text-sm font-medium text-slate-900">${statement.id_benefit_eligible ? 'Exempt' : money(statement.tax_amount)}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-slate-500">Balance</dt><dd class="mt-0.5 text-base font-semibold text-slate-900">${money(statement.balance_amount)}</dd></div>
                    </dl>
                    <details class="mt-4 rounded-lg border border-slate-200 px-3">
                        <summary class="cursor-pointer py-3 text-sm font-medium text-blue-700">Itemized charges (${(statement.charges || []).length})</summary>
                        <div class="pb-2">${chargeGroups || '<p class="py-2 text-sm text-slate-500">No charges recorded.</p>'}</div>
                    </details>
                    ${(statement.take_home_medicines || []).length ? `
                        <section class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <h4 class="text-sm font-semibold text-emerald-900">Medicines Taken Home</h4>
                            <p class="mt-1 text-xs text-emerald-800">Included in the pharmacy charges above; no additional charge is added here.</p>
                            <ul class="mt-2 space-y-1 text-sm text-emerald-950">
                                ${statement.take_home_medicines.map(medicine => `
                                    <li>${escapeHtml(medicine.item_name)} — ${Number(medicine.quantity_take_home)}${medicine.unit_of_measure ? ` ${escapeHtml(medicine.unit_of_measure)}` : ' units'}</li>
                                `).join('')}
                            </ul>
                        </section>
                    ` : ''}
                    <a target="_blank" rel="noopener" href="${baseUrl}/patient-statement.php?statement_id=${Number(statement.statement_id)}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">View Statement</a>
                </article>`;
            }).join('')
            : '<p class="px-4 py-8 text-center text-sm text-slate-500">No billing statements available.</p>';
        const admissions = data.admissions.length
            ? data.admissions.map(admission => `<tr class="border-t border-slate-100">
                    <td class="px-4 py-3">Admission #${Number(admission.admission_id)}</td>
                    <td class="px-4 py-3">${escapeHtml(admission.chief_complaint || 'No reason recorded')}</td>
                    <td class="px-4 py-3">${escapeHtml(date(admission.admission_datetime))}${admission.discharge_datetime ? ` – ${escapeHtml(date(admission.discharge_datetime))}` : ''}</td>
                    <td class="px-4 py-3">${escapeHtml(admission.status)}</td>
                </tr>`).join('')
            : '<tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">No admissions recorded.</td></tr>';
        const diagnoses = data.diagnoses.length
            ? data.diagnoses.map(diagnosis => `
                <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0">
                    <div><p class="font-medium text-slate-900">${escapeHtml(diagnosis.diagnosis_name)}</p>
                    <p class="text-xs text-slate-500">${escapeHtml(diagnosis.diagnosis_type || 'Diagnosis')}${diagnosis.icd_code ? ` · ${escapeHtml(diagnosis.icd_code)}` : ''} · Admission #${Number(diagnosis.admission_id)}</p></div>
                    <time class="text-xs text-slate-500">${escapeHtml(date(diagnosis.diagnosed_datetime))}</time>
                </li>`).join('')
            : '<li class="text-sm text-slate-500">No diagnoses recorded.</li>';
        const consultations = data.consultations.length
            ? data.consultations.map(consultation => `
                <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-slate-100 py-3 first:border-0 first:pt-0 last:pb-0">
                    <div><p class="font-medium text-slate-900">${escapeHtml(consultation.purpose || 'Consultation')}</p>
                    <p class="text-xs text-slate-500">Dr. ${escapeHtml(`${consultation.doctor_first_name} ${consultation.doctor_last_name}`)} · ${escapeHtml(consultation.status || 'Recorded')}</p></div>
                    <time class="text-xs text-slate-500">${escapeHtml(date(consultation.consultation_datetime))}</time>
                </li>`).join('')
            : '<li class="text-sm text-slate-500">No consultations recorded.</li>';

        content.innerHTML = `
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-sm font-medium text-blue-700">Patient Portal · Your documents</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">${escapeHtml(`${patient.first_name} ${patient.last_name}`)}</h1>
                    <p class="mt-1 text-sm text-slate-600">Patient code ${escapeHtml(patient.patient_code || '—')} · Date of birth ${escapeHtml(date(patient.birth_date))}</p>
                    <p id="patientPortalWebsocketStatus" class="mt-1 text-xs text-emerald-700">Live updates: ${escapeHtml(websocketStatus)}</p></div>
                <button id="patientPortalLogout" type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sign out</button>
            </div>
            <section class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Billing statements</h2><p class="mt-1 text-xs text-slate-500">Open a statement to view, print, or download your cashier-format Statement of Account.</p></div>
                <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs font-medium uppercase text-slate-500"><tr>
                    <th class="px-4 py-3">Statement</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3 text-right">Statement of Account</th>
                </tr></thead><tbody>${statementRows}</tbody></table></div>
                <div class="divide-y divide-slate-100 md:hidden">${statementCards}</div>
            </section>
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Other available records</h2>
            <section class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold text-slate-900">Admissions</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs font-medium uppercase text-slate-500"><tr>
                    <th class="px-4 py-3">Admission</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3">Status</th>
                </tr></thead><tbody>${admissions}</tbody></table></div>
            </section>
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-semibold text-slate-900">Medical history and diagnoses</h3>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">${escapeHtml(patient.medical_history || 'No medical history recorded.')}</p>
                    <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100">${diagnoses}</ul>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-semibold text-slate-900">Consultations</h3><ul class="mt-3 divide-y divide-slate-100">${consultations}</ul></section>
            </div>
            <p class="mt-6 text-center text-xs text-slate-500">This portal session automatically ends after five minutes without activity.</p>`;

        loading.classList.add('hidden');
        content.classList.remove('hidden');
        document.getElementById('patientPortalLogout').addEventListener('click', logout);
    }

    function beginIdleTimeout() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(async () => {
            try {
                await request('logout', { method: 'POST', body: {} });
            } catch (error) {
                console.error('[patient-portal] Could not destroy expired session.', error);
            }
            showLogin();
            await Swal.fire({
                icon: 'info',
                title: 'Session expired',
                text: 'For your privacy, sign in again to continue.',
            });
        }, 300000);
    }

    function recordActivity() {
        const now = Date.now();
        if (now - lastActivity < 1000) return;
        lastActivity = now;
        if (document.body.dataset.authenticated === '1') beginIdleTimeout();
    }

    async function logout() {
        try {
            await request('logout', { method: 'POST', body: {} });
            showLogin();
            await Swal.fire({ icon: 'success', title: 'Signed out', timer: 1200, showConfirmButton: false });
        } catch (error) {
            await Swal.fire({ icon: 'error', title: 'Could not sign out', text: error.message });
        }
    }

    function showLogin() {
        clearTimeout(idleTimer);
        clearTimeout(patientRecordsRefreshTimer);
        closePatientSocket();
        document.body.dataset.authenticated = '0';
        recordsSection.classList.add('hidden');
        loginSection.classList.remove('hidden');
        content.classList.add('hidden');
        loginForm.reset();
        loginError.classList.add('hidden');
        refreshCaptcha().catch(error => showLoginError(error.message));
    }

    async function loadPatientRecords() {
        if (recordsRefreshInProgress) {
            recordsRefreshRequested = true;
            return;
        }
        recordsRefreshInProgress = true;
        try {
            const result = await request('records');
            renderPatientRecords(result.data);
            document.body.dataset.authenticated = '1';
        } catch (error) {
            if (error.status === 401) {
                showLogin();
            } else {
                console.error('[patient-portal] Could not refresh patient records.', error);
                if (!content.classList.contains('hidden')) {
                    loading.innerHTML = `<p class="text-rose-700">${escapeHtml(error.message)}</p>`;
                } else {
                    loading.innerHTML = `<p class="text-rose-700">${escapeHtml(error.message)}</p><button id="patientPortalRetry" type="button" class="mt-3 rounded-lg border px-3 py-2 text-sm">Try again</button>`;
                    document.getElementById('patientPortalRetry').addEventListener('click', loadPatientRecords);
                }
            }
        } finally {
            recordsRefreshInProgress = false;
            if (recordsRefreshRequested) {
                recordsRefreshRequested = false;
                window.setTimeout(loadPatientRecords, 100);
            }
        }
    }

    loginForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!captchaReady) {
            showLoginError('Please wait for the security question to load, then try again.');
            return;
        }
        loginError.classList.add('hidden');
        loginButton.disabled = true;
        loginButton.textContent = 'Signing in…';
        try {
            await request('login', {
                method: 'POST',
                body: Object.fromEntries(new FormData(loginForm).entries()),
            });
            document.body.dataset.authenticated = '1';
            recordActivity();
            loginSection.classList.add('hidden');
            recordsSection.classList.remove('hidden');
            loading.classList.remove('hidden');
            content.classList.add('hidden');
            await loadPatientRecords();
            if (document.body.dataset.authenticated === '1') {
                connectPatientSocket();
                await Swal.fire({ icon: 'success', title: 'Signed in', toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
            }
        } catch (error) {
            showLoginError(error.message);
            captchaAnswer.value = '';
            await refreshCaptcha().catch(refreshError => showLoginError(refreshError.message));
        } finally {
            loginButton.disabled = !captchaReady;
            loginButton.textContent = 'Sign in securely';
        }
    });

    document.getElementById('refreshCaptcha').addEventListener('click', () => {
        refreshCaptcha().catch(error => showLoginError(error.message));
    });

    for (const eventName of ['pointerdown', 'pointermove', 'keydown', 'touchstart']) {
        document.addEventListener(eventName, recordActivity, { passive: true });
    }

    setInterval(() => {
        if (document.body.dataset.authenticated !== '1' || Date.now() - lastActivity >= 300000) return;
        request('touch').catch(error => {
            if (error.status === 401) showLogin();
        });
    }, 60000);

    setInterval(() => {
        if (document.body.dataset.authenticated === '1') loadPatientRecords();
    }, 10000);

    refreshCaptcha().catch(error => showLoginError(error.message));
    if (document.body.dataset.authenticated === '1') {
        loadPatientRecords();
        connectPatientSocket();
    }
})();
