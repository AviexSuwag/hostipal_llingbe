# Billing Hospital

## Local setup

1. Create the `billing_hospital` MySQL database and import the schema-only `billing_hospital.sql`. It does not include patient, billing, or account seed records; provision required master data and an initial administrator securely for your environment.
2. Configure the database connection in `config/connection.php` for your local MySQL account.
3. Install PHP dependencies with `composer install`.
4. Install JavaScript dependencies with `npm install`. The post-install script generates the local browser vendor copies.
5. Build the Tailwind stylesheet with `npm run tailwind:build`.
6. Apply the SQL files in `database/migrations/` once, in filename order.
7. Start Apache and MySQL in XAMPP, then run `start-websocket.bat` in a separate console.

The application expects to be served from `/billing_hospital` by default; update `BASE_URL` in `config/config.php` if your local URL differs.

## Staff access

Staff sign in through the portal URL for their assigned role: `/admin-login.php`, `/doctor-login.php`, `/nurse-login.php`, `/cashier-login.php`, or `/department-login.php`. The site root links to the patient portal; `index.php` remains the application router for authenticated staff pages. The login API verifies that the authenticated account's assigned role matches the selected portal, and the application continues enforcing role-specific page and API access after sign-in.

Staff dropdowns can be filtered by typing in the search field above each list. Free-text “Other” choices are available for patient ID type and charge-item units; foreign-key choices use the system's managed master lists.

## Live updates

The realtime notification and update service runs separately from Apache. Start it with `start-websocket.bat` and leave its console window running while using the application. WebSocket clients connect on port `8090` by default (`WEBSOCKET_PORT` can override it for both the PHP server and Apache). The same process binds the patient-portal in-memory sign-in rate limiter to loopback port `8081`. Allow the configured WebSocket port through the local firewall when accessing the application from another device; do not expose port `8081`. Port `8080` is commonly used by Tomcat and is not the default here to avoid conflicts.

For HTTPS deployments, configure the web server to proxy secure WebSocket (`wss`) connections to this service.

## Patient portal

Patients sign in at `/billing_hospital/patient-portal.php` with their patient code, first name, last name, date of birth, and the on-page math CAPTCHA. The patient code is displayed after admission is successfully created. The mobile-friendly portal refreshes records when a relevant WebSocket event arrives, with a periodic refresh as a fallback; open patient statements also refresh automatically when their bill changes. The WebSocket server sends patient portals only a data-free refresh signal for their subscribed admissions/statements. The portal does not store a password or date of birth in the session. Sign-ins are limited to ten attempts per IP address in a 15-minute window by the in-memory limiter, so the local WebSocket/limiter process must be running. Patient sessions expire after five minutes of inactivity.

Administrators configure room rates as one-time, hourly, or daily charges. Emergency Room charges support the same billing intervals and continue until their configured end time, patient discharge, or the present time. Recording both an ID type and number automatically applies a 20% discount and VAT exemption, regardless of age; leaving both ID fields empty means no automatic ID-based discount.

Medicine dose tracking and return credits remain available without pharmacy inventory tracking. Nursing records received and administered quantities; at discharge readiness, the patient may take remaining medicine home or return it. Pharmacy confirms the quantity actually received, and the successfully fulfilled return credits that amount at the original unit price. No stock-on-hand quantity is checked, reduced, or restocked. The Pharmacy Stock page and stock adjustment endpoint are retired.

Apply the SQL files that are present in `database/migrations/` once, in filename order, after importing the base schema. `database/migrations/002_patient_billing_revision.sql` adds patient codes, ID evidence, configurable charge intervals, and the medicine dose-tracking flag. `database/migrations/003_billing_deduction_percentages.sql` adds percentage-based deduction rates and converts existing deduction amounts to equivalent rates. Deduction percentages are calculated automatically from each statement's subtotal; verified patient ID applies the 20% government discount and VAT exemption automatically.

`database/migrations/004_medicine_disposition_billing_details.sql` records medicines selected for take-home at discharge readiness. For each medicine, the doctor can enter a quantity to return; any remaining eligible quantity is recorded as take-home. Billing statements show successful Pharmacy returns as negative credit lines and list take-home quantities as informational details included in the original medicine charges.
