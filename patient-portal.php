<?php
require_once __DIR__ . '/includes/patient_portal_session.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/websocket-config.php';

$authenticated = !empty($_SESSION['patient_portal']['patient_id']);
$pageTitle = 'Patient Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eff6ff">
    <title><?= htmlspecialchars($pageTitle) ?> · Billing Hospital</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sweetalert2.min.css">
    <script src="<?= BASE_URL ?>/assets/js/vendor/sweetalert2.all.min.js"></script>
</head>
<body data-base-url="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>"
      data-authenticated="<?= $authenticated ? '1' : '0' ?>"
      class="min-h-screen bg-slate-100 px-4 py-6 font-sans antialiased sm:py-10">
    <main class="mx-auto w-full max-w-5xl">
        <header class="mb-6 rounded-2xl bg-gradient-to-br from-blue-700 to-indigo-800 p-6 text-white shadow-sm sm:mb-8 sm:p-8">
            <p class="text-sm font-semibold text-blue-100">Billing Hospital</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Patient Portal</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base">Securely view your current and past statements, itemized charges, payments, and available patient records.</p>
        </header>

        <section id="patientPortalLogin" class="<?= $authenticated ? 'hidden ' : '' ?>rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="mx-auto max-w-xl">
                <h2 class="text-lg font-semibold text-slate-900">Sign in to your account</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Enter the patient code issued at admission and verify your name and birth date.</p>
                <div id="portalLoginError" class="hidden mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></div>
                <form id="patientPortalLoginForm" class="mt-5 space-y-4" novalidate>
                    <div>
                        <label for="patientCode" class="block text-sm font-medium text-slate-700">Patient Code</label>
                        <input id="patientCode" name="patient_code" type="text" required maxlength="15" autocomplete="off"
                               placeholder="PH-XXXXXXXXXXXX" autocapitalize="characters"
                               class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base uppercase tracking-wide focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="firstName" class="block text-sm font-medium text-slate-700">First Name</label>
                            <input id="firstName" name="first_name" type="text" required maxlength="100" autocomplete="given-name"
                                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="lastName" class="block text-sm font-medium text-slate-700">Last Name</label>
                            <input id="lastName" name="last_name" type="text" required maxlength="100" autocomplete="family-name"
                                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label for="birthDate" class="block text-sm font-medium text-slate-700">Date of Birth</label>
                        <input id="birthDate" name="birth_date" type="date" data-date-entry data-date-max-today="true" required autocomplete="bday"
                               class="mt-1.5 w-full rounded-lg border border-slate-300 px-3.5 py-3 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <label for="captchaAnswer" class="block text-sm font-medium text-slate-700">Security question</label>
                        <div class="mt-2 flex items-center gap-3">
                            <span id="captchaQuestion" class="min-w-20 text-center text-lg font-semibold text-slate-900" aria-live="polite">Loading…</span>
                            <span aria-hidden="true" class="text-slate-400">=</span>
                            <input id="captchaAnswer" name="captcha_answer" type="number" inputmode="numeric" required
                                   class="w-24 rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button id="refreshCaptcha" type="button" class="ml-auto rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">New question</button>
                        </div>
                    </div>
                    <button id="patientPortalLoginButton" type="submit" disabled
                            class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                        Sign in securely
                    </button>
                </form>
                <p class="mt-5 text-xs leading-5 text-slate-500">For your privacy, your name and date of birth are checked with your patient code. Sessions end after five minutes of inactivity.</p>
            </div>
        </section>

        <section id="patientPortalRecords" class="<?= $authenticated ? '' : 'hidden ' ?>space-y-5">
            <div id="patientPortalLoading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500" role="status">Loading your records…</div>
            <div id="patientPortalContent" class="hidden"></div>
        </section>

        <footer class="mt-8 text-center text-xs text-slate-500">
            If you need help accessing your account, please contact the hospital.
        </footer>
    </main>
    <script>window.HOSPITAL_WEBSOCKET_PORT = <?= WEBSOCKET_PORT ?>;</script>
    <script src="<?= BASE_URL ?>/assets/js/mobile-layout.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/mobile-layout.js') ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/date-entry.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/date-entry.js') ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/patient-portal.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/patient-portal.js') ?>"></script>
</body>
</html>
