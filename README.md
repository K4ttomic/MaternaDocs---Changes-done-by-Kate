# MaternaDocs

Web app for a birthing home (bahay paanakan / lying-in clinic). Staff enter patient data **once**; the system validates it, stores it, and (later) auto-fills document templates (PDF/DOCX) for download. Entering data once and reusing it across forms is what removes most human error. Patients can log in to view their own records.

Course project: IT 314 Web Systems and Technologies (team project, "our client" is the clinic). **Timeline: 6 weeks** (compressed from the original 17-week plan).

> \*\*For AI agents:\*\* read this whole file before changing anything. Keep it current: after every work session, update \*\*Current Status\*\*, \*\*Progress Log\*\*, and \*\*Roadmap\*\* (see "Maintaining this file").

\---

## Tech stack

* PHP (plain, no framework), MySQL (PDO, prepared statements only)
* HTML5, CSS3, Bootstrap 5.3 (CDN), Bootstrap Icons (CDN), vanilla JS
* Fonts: Fraunces (headings), DM Sans (body) via Google Fonts
* Local environment: XAMPP on Windows, served at `http://localhost/MaternaDocs`

## Roles (RBAC)

|Role (DB value)|Label|Access|
|-|-|-|
|`admin`|Administrator|Everything staff can do, plus user accounts, templates, audit log|
|`front\_desk`|Front Desk Staff|Patient records, forms \& documents, dashboard|
|`patient`|Patient|Only her own records and documents (linked via `patients.user\_id`)|

Sidebar menus per role are defined in the `NAV` constant in `includes/auth.php` (a `null` href renders as "soon"). New logged-in pages use `shell\_start($u, 'Title')` / `shell\_end()`. Enforce access on every page with `require\_role(\[...])`; never rely on hidden menu items alone.

## Project structure

```
MaternaDocs/
├── index.php            Landing page (public)
├── login.php            Patient sign-in by default; staff/admin sign-in (`?portal=staff`) is hidden, see "Hidden staff sign-in"
├── logout.php           Ends session, logs the action
├── dashboard.php        Role-based dashboard (placeholder data for now)
├── settings.php         Account settings for every role: change own username and password
├── patients.php         Admin + front desk: patient list and search (name or PhilHealth no.), links to add/edit

├── patient\_form.php     Admin + front desk: add/edit a patient with server-side validation and duplicate detection

├── users.php            Admin only: create accounts (name, username, role), reset password, activate/deactivate, unlock
├── setup.php            ONE-TIME DB creation + seed. Must be deleted after use.
├── database/schema.sql  Full schema (users, patients, audit\_log)

├── database/migrations/ One-off SQL for existing databases (run in phpMyAdmin)
├── includes/
│   ├── db.php           PDO connection: db()
│   └── auth.php         Session, RBAC, CSRF, audit helpers, page\_head()/page\_foot(), logged-in layout shell\_start()/shell\_end()
├── assets/css/style.css Theme + all page styles
└── assets/js/hidden.js  Hidden-click trigger for the staff sign-in
```

## Setup (local)

1. Start **Apache and MySQL** in the XAMPP Control Panel.
2. Place the project in `C:\\xampp\\htdocs\\MaternaDocs\\` keeping the folder layout above.
3. Visit `localhost/MaternaDocs/setup.php` once (creates DB `maternadocs`, tables, 3 starter accounts).
4. **Delete `setup.php`**, then change the starter passwords.
5. Patients sign in at `localhost/MaternaDocs/login.php`. Staff/admin use the hidden entry (see "Hidden staff sign-in").

Existing database created before the username change? Run once in phpMyAdmin (SQL tab):

```sql
USE maternadocs;
ALTER TABLE users CHANGE email username VARCHAR(30) NOT NULL;
UPDATE users SET username='admin' WHERE username='admin@materna.test';
UPDATE users SET username='staff' WHERE username='staff@materna.test';
UPDATE users SET username='patient' WHERE username='patient@materna.test';
```

Default usernames: **`admin`**, **`staff`**, **`patient`** (initial passwords `admin123`, `staff123`, `patient123`). Login uses a **username**, not an email. The project owner will change these usernames and passwords himself/herself from Settings; do not hard-code or assume them anywhere.

DB credentials are XAMPP defaults in `includes/db.php` (`root`, empty password). Change before any real deployment.

## Database

* **users**: `username` (unique, lowercase letters/digits/dot/underscore, 3-30 chars; changeable by the user in Settings), `password\_hash`, `first\_name`, `middle\_name`, `last\_name`, `role`, `is\_active`, `failed\_attempts`, `locked\_until`, `last\_login\_at`
* **patients**: `user\_id` (nullable unique FK to users), `first\_name`, `middle\_name`, `last\_name`, `birthdate`, `civil\_status`, `philhealth\_no` (unique, stored as XX-XXXXXXXXX-X), `address`, `contact\_no`, `emergency\_contact\_name`, `emergency\_contact\_no`, `lmp`, `edd`, `gravida`, `para`, `created\_by`, timestamps
* **audit\_log**: `user\_id`, `action`, `detail`, `ip`, `created\_at`

Only the **stable core fields** exist so far (see Decisions). Add form-specific columns through a migration (`ALTER TABLE`), not by recreating tables.

## Hidden staff sign-in

The public landing page and `login.php` show only the patient sign-in; there is no staff/admin link anywhere. To reach the staff/admin sign-in, **click the copyright text in the landing page footer, or the "Welcome back" heading on the patient sign-in page, 5 times within 3 seconds** (script: `assets/js/hidden.js`, target set by `data-secret-link`). It opens `login.php?portal=staff`. Staff who sign out are returned to that page. This is obscurity only: the real protection is credentials, the 5-attempt lockout, portal enforcement and the audit log. Anyone who knows `?portal=staff` can open the form; do not treat it as a secret.

## Security conventions (keep these)

* Prepared statements for all SQL; escape all output with `e()`.
* Passwords: `password\_hash()` / `password\_verify()`.
* CSRF token on every POST form (`csrf\_token()` / `csrf\_ok()`).
* `session\_regenerate\_id(true)` on login; `HttpOnly` + `SameSite=Lax` session cookie.
* Login errors are generic ("Invalid credentials for this portal"), never revealing which part was wrong.
* Account locks for 15 minutes after 5 failed attempts.
* Portals are enforced server-side: patient accounts cannot sign in via the Staff portal and vice versa.
* Passwords need 8+ characters with a letter and a number. Admin-created/reset temporary passwords are shown once. Decision: staff and admin can edit a patient's name (patients cannot edit their own name). Decision: no forced password change for any role; every account changes its own password from Settings.
* Log meaningful actions with `log\_action($userId, $action, $detail)`.
* Patients must only ever read rows tied to their own `user\_id`.

## Design system

Green palette (ColorBrewer Greens), defined as CSS variables at the top of `assets/css/style.css`. Variable names are legacy: `--teal` = main green `#238b45`, `--teal-d` = `#005a32`, `--coral` = accent `#41ab5d`, `--blush` = page background `#f2faef`. Unused palette greens: `#a1d99b`, `#74c476`. On dark green panels use `#a1d99b` for icons (the accent has poor contrast there). Layouts must stay responsive (tested via browser device mode and real phone on the same Wi-Fi).

## Decisions and constraints

* **Templates are not available yet.** Decision: do **no** template-related work (no dummy templates, no document generation, no form-specific fields) until the clinic provides the real templates/forms. Reason: a mismatch would force rework. The team is asking the clinic for blank copies of their common forms.
* Patient table holds only stable core fields until templates arrive. Exception: `philhealth\_no`, added because the proposal requires PhilHealth ID validation. The clinic has since provided the PhilHealth CSF (Claim Signature Form); form-specific fields and document generation are still deliberately not started and should be a separate, scoped step.
* **IoT feature (chosen):** monitor room temperature and humidity in patient rooms (important for newborns). Planned as a monitoring page with per-room readings and out-of-range alerts, fed by simulated data first; a sensor (for example ESP32 + DHT22) would later POST readings to a PHP endpoint. Not started.
* No framework; keep the code simple enough for a student team to explain.
* &#x20;Patient form validation limits (age 10-60, EDD within 300 days of LMP, gravida/para max 20) are constants at the top of `patient\_form.php`; confirm them with the clinic.

## Current status

* Done: Settings page (change username and password, all roles) and admin user management (create, reset password, activate/deactivate, unlock); landing page, login (staff/admin and patient portals), logout, role-based dashboard shell with placeholder data, green theme, MySQL schema, real database login with lockout and audit logging. Patient records module: add/edit patient with validation and duplicate detection (`patient\_form.php`), searchable list (`patients.php`).
* Not yet tested by the team at time of writing: database setup, login against MySQL, and the new patient form. Existing databases must run `database/migrations/2026-10-30\_add\_philhealth\_no.sql` first.
* Dashboard numbers are placeholders (`—`). Sidebar items marked "soon" are not built.

## Roadmap (in order)

1. ~~Database + real login~~ (built; pending team verification)
2. Patient records module (built: `patients.php` + `patient\_form.php`, core fields only; pending team verification). Still to do: delete patient (admin only), link a patient record to a portal account.
3. ~~Admin user account management~~ (built: `users.php`). Still to do: audit log viewer; link patient records to patient user accounts.
4. Patient portal: "My Records" showing only the logged-in patient's data.
5. Room temperature/humidity monitoring page (IoT), simulated data first.
6. Real dashboard statistics.
7. **Blocked until templates arrive:** form-specific fields, document templates, auto-fill, PDF/DOCX download.

## Progress log

Newest at the bottom. One entry per work session: date, what changed, files touched.

* **2026-10-16** Initial build: landing page (`index.php`), login (`login.php`), role-based dashboard shell (`dashboard.php`), `includes/auth.php`, `assets/css/style.css`. Retheme to green palette; fixed low-contrast logo icon on login side panel.
* **2026-10-16** Backend foundation: `database/schema.sql`, `includes/db.php`, `setup.php`; login/logout rewritten to use MySQL with prepared statements, lockout after 5 failures, and audit logging; demo-credentials box removed from login page.
* **2026-10-16** Added `settings.php` (every role: account info + change own password; Settings button in top bar) and `users.php` (admin: create account, reset password, activate/deactivate, unlock). Added shared `shell\_start()/shell\_end()` layout in `includes/auth.php`; `dashboard.php` uses it; `NAV` entries carry hrefs. Decision: no forced password change for any role. Not yet tested by the team.
* **2026-10-18** Switched login from email to **username** (`users.email` renamed to `users.username`); users can change their own username (needs current password) and password in `settings.php`; `users.php` create form takes a username; `valid\_username()` added to `includes/auth.php`; default usernames are admin, staff, patient. Files: `schema.sql`, `setup.php`, `login.php`, `users.php`, `settings.php`, `includes/auth.php`. Not yet tested by the team.
* **2026-10-18** Names split into first, middle (optional) and surname: `users` now has `first\_name`/`middle\_name`/`last\_name` (replaces `full\_name`; helpers `compose\_name()`, `valid\_name()` in `includes/auth.php`). New `patients.php`: admin and front desk can list/search patients and edit a patient's name; it also syncs the linked portal account's name. `settings.php`: admin/front desk edit their own name; patients see their name read-only (ask the front desk). `users.php` create form uses the three name fields. Existing databases need the migration below. Not yet tested by the team.

Migration for existing databases (run once in phpMyAdmin, SQL tab):

```sql
USE maternadocs;
ALTER TABLE users
  ADD COLUMN first\_name VARCHAR(80) NOT NULL DEFAULT '' AFTER password\_hash,
  ADD COLUMN middle\_name VARCHAR(80) NULL AFTER first\_name,
  ADD COLUMN last\_name VARCHAR(80) NOT NULL DEFAULT '' AFTER middle\_name;
UPDATE users SET first\_name = SUBSTRING\_INDEX(full\_name, ' ', 1),
  last\_name = TRIM(SUBSTRING(full\_name, LENGTH(SUBSTRING\_INDEX(full\_name, ' ', 1)) + 1));
ALTER TABLE users DROP COLUMN full\_name;
```

* **2026-10-18** Hid the staff/admin sign-in: removed the Staff tab and the landing-page "Staff login" button; `login.php` defaults to the patient portal; a 5-quick-clicks trigger (`assets/js/hidden.js`, loaded from `page\_foot()`) on the footer text and the "Welcome back" heading opens `?portal=staff`; `logout.php` sends staff back to the staff sign-in. Files: `index.php`, `login.php`, `logout.php`, `includes/auth.php`, `assets/css/style.css`, `assets/js/hidden.js`. Not yet tested by the team.
* **2026-10-30** Patient records module. New `patient\_form.php` (add + edit all core fields) with server-side validation: required names and birthdate, birthdate not in the future and age 10-60, LMP not in the future, EDD after LMP and within 300 days, gravida/para whole numbers 0-20 with para <= gravida, PH mobile format (09XXXXXXXXX), PhilHealth number 12 digits, emergency contact name and number together, and duplicate detection (same name + birthdate, or same PhilHealth no.). If LMP is given and EDD is blank, EDD is computed as LMP + 280 days. `patients.php` rewritten: list, search by name or PhilHealth no., Add/Edit buttons (the old name-only editor is replaced by the full form; the portal account name still syncs on edit). Added `patients.philhealth\_no` (the proposal requires PhilHealth ID validation). Audit log entries: `patient\_created`, `patient\_updated`. Files: `patient\_form.php`, `patients.php`, `database/schema.sql`, `database/migrations/2026-10-30\_add\_philhealth\_no.sql`. Not yet tested by the team.
* 

Migration for existing databases (run once in phpMyAdmin, SQL tab):

```sql

USE maternadocs;

ALTER TABLE patients ADD COLUMN philhealth\_no VARCHAR(14) NULL UNIQUE AFTER civil\_status;

```

## Maintaining this file

After each session: (1) add a dated entry to the Progress log, (2) update Current status and tick or reorder the Roadmap, (3) record any new decision or constraint under Decisions, (4) update Project structure and Database if files or tables changed. Keep entries short and factual.

