<?php


require_once __DIR__ . '/../../controllers/NurseController.php';

$controller = new NurseController($pdo);
$genders = $controller->getGenders();
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Register New Patient</h1>
    <p class="mt-1 text-sm text-slate-500">Enter the patient's personal information to create a new record.</p>
</div>

<div id="alert" class="hidden mb-5 rounded-lg px-4 py-3 text-sm border"></div>

<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form id="registerForm" method="POST" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">First Name <span class="text-rose-500">*</span></label>
                <input type="text" id="first_name" name="first_name" required
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="first_name"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Last Name <span class="text-rose-500">*</span></label>
                <input type="text" id="last_name" name="last_name" required
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="last_name"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Gender <span class="text-rose-500">*</span></label>
                <select id="gender_id" name="gender_id" required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Select gender —</option>
                    <?php foreach ($genders as $g): ?>
                        <option value="<?= (int)$g['gender_id'] ?>"><?= htmlspecialchars($g['gender_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="gender_id"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Birth Date</label>
                <input type="date" id="birth_date" name="birth_date" data-date-entry data-date-max-today="true"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Age</label>
                <input type="text" id="patient_age" readonly placeholder="Enter birth date"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">
            </div>

            <div class="sm:col-span-2 rounded-lg border border-blue-100 bg-blue-50 p-4">
                <h2 class="text-sm font-semibold text-slate-900">ID evidence for discount</h2>
                <p class="mt-1 text-xs text-slate-600">Optional. Recording both an ID type and ID number automatically applies a 20% discount and VAT exemption. Leave both ID fields empty for no automatic discount. PWD ID is included in the accepted ID list.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5" for="senior_id_type">ID Type</label>
                        <select id="senior_id_type" name="senior_id_type" data-searchable="false"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">No ID provided</option>
                            <option>Philippine National ID (PhilID)</option>
                            <option>Digital ePhilID / eGovPH ID</option>
                            <option>Philippine Passport</option>
                            <option>Driver’s License</option>
                            <option>Unified Multi-Purpose ID (UMID)</option>
                            <option>PRC ID</option>
                            <option>Digitized SSS / GSIS ID</option>
                            <option>Postal ID (PVC card version)</option>
                            <option>School ID (for current students)</option>
                            <option>Seaman’s Record Book (SRB)</option>
                            <option>Alien Certificate of Registration (ACR) / Immigrant Certificate of Registration (ICR)</option>
                            <option>TIN Card</option>
                            <option>PhilHealth Card</option>
                            <option>Pag-IBIG Loyalty Card</option>
                            <option>Voter’s ID / Voter’s Certificate</option>
                            <option>Senior Citizen ID</option>
                            <option>PWD ID</option>
                            <option>Solo Parent ID</option>
                            <option>NBI Clearance</option>
                            <option>Police Clearance</option>
                            <option>Barangay Certification / ID</option>
                            <option>Company / Employee ID</option>
                            <option>PSA Birth Certificate</option>
                            <option value="Other">Other</option>
                        </select>
                        <p class="mt-1.5 hidden text-xs text-red-600" data-error-for="senior_id_type"></p>
                    </div>
                    <div id="otherIdTypeContainer" class="hidden">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5" for="senior_id_other_type">Other ID Type</label>
                        <input type="text" id="senior_id_other_type" name="senior_id_other_type" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mt-1.5 hidden text-xs text-red-600" data-error-for="senior_id_other_type"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5" for="senior_id_number">ID Number</label>
                        <input type="text" id="senior_id_number" name="senior_id_number" maxlength="100"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mt-1.5 hidden text-xs text-red-600" data-error-for="senior_id_number"></p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input type="email" id="email" name="email"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="email"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
                <input type="text" id="address" name="address"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Emergency Contact</label>
                <input type="text" id="emergency_contact" name="emergency_contact"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Emergency Contact Number</label>
                <input type="text" id="emergency_contact_number" name="emergency_contact_number"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Medical History</label>
                <textarea id="medical_history" name="medical_history" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="<?= BASE_URL ?>/index.php?page=nurse-patient-search"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
            <button type="submit" id="submitBtn"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                <span id="submitLabel">Register Patient</span>
            </button>
        </div>
    </form>
</div>

<script>
(function initializePatientRegistrationForm() {
const birthDateInput = document.getElementById('birth_date');
const ageInput = document.getElementById('patient_age');
const today = new Date();
birthDateInput.max = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0'),
].join('-');

function updatePatientAge() {
    const match = birthDateInput.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        ageInput.value = '';
        return;
    }

    const [, year, month, day] = match.map(Number);
    const birthDate = new Date(year, month - 1, day);
    if (birthDate.getFullYear() !== year || birthDate.getMonth() !== month - 1 || birthDate.getDate() !== day) {
        ageInput.value = '';
        return;
    }

    const now = new Date();
    let age = now.getFullYear() - year;
    if (now.getMonth() < month - 1 || (now.getMonth() === month - 1 && now.getDate() < day)) age--;
    ageInput.value = age >= 0 ? `${age} years` : '';
}

birthDateInput.addEventListener('input', updatePatientAge);
birthDateInput.addEventListener('change', updatePatientAge);
updatePatientAge();

document.getElementById('senior_id_type').addEventListener('change', event => {
    const other = event.target.value === 'Other';
    document.getElementById('otherIdTypeContainer').classList.toggle('hidden', !other);
    document.getElementById('senior_id_other_type').required = other;
});

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    const label = document.getElementById('submitLabel');
    const alertEl = document.getElementById('alert');

    btn.disabled = true;
    label.textContent = 'Registering…';
    alertEl.classList.add('hidden');

    document.querySelectorAll('[data-error-for]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    try {
        const baseUrl = document.body.dataset.baseUrl;
        const response = await axios.post(`${baseUrl}/api/nurses/patient-register.php`, data, {
            headers: { 'Content-Type': 'application/json' }
        });

        if (response.data.success) {
            alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border border-emerald-200 bg-emerald-50 text-emerald-800';
            alertEl.textContent = response.data.message;
            alertEl.classList.remove('hidden');

            setTimeout(() => {
                window.location.href = `${baseUrl}/index.php?page=nurse-admission-create&patient_id=${response.data.patient_id}`;
            }, 1000);
        }
    } catch (err) {
        const res = err.response?.data;
        if (res?.errors) {
            Object.entries(res.errors).forEach(([field, msg]) => {
                const el = document.querySelector(`[data-error-for="${field}"]`);
                if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            });
        }
        alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border border-rose-200 bg-rose-50 text-rose-800';
        alertEl.textContent = res?.message || 'Something went wrong.';
        alertEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        label.textContent = 'Register Patient';
    }
});
})();
</script>