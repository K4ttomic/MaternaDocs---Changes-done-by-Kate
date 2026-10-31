# MaternaDocs
Added a patient add/edit form (patient_form.php) with server-side validation (dates, age, LMP/EDD, gravida/para, phone, PhilHealth format), duplicate detection and auto-EDD. Updated the patient list with search and Add/Edit buttons. Added a philhealth_no column, a migration, starter accounts SQL and audit logging.

Add and edit patients: patient_form.php is a new form for all the core patient fields.
Validation: the server checks everything before saving (required fields, dates, age, LMP/EDD logic, gravida/para, phone and PhilHealth formats) and shows errors under the bad fields.
Duplicate detection: it blocks the same name and birthdate, or the same PhilHealth number.
Auto-EDD: if you enter an LMP and leave EDD blank, EDD is filled in as LMP + 280 days.
Patient list: patients.php now has search by name or PhilHealth number, plus Add and Edit buttons.
Database: a new philhealth_no column on patients, with a migration file and an updated schema.sql.
Setup helper: seed_users.sql creates the starter accounts, so you can skip setup.php.
Audit log: adding or editing a patient is logged.
README: updated with the new files, decisions, status and progress log.

Not built yet: document generation from the CSF form, dashboard counts, the audit log viewer, deleting patients, and linking patients to portal accounts.
