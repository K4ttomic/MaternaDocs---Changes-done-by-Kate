-- Run once in phpMyAdmin (SQL tab) on an EXISTING database.
USE maternadocs;
ALTER TABLE patients
  ADD COLUMN philhealth_no VARCHAR(14) NULL UNIQUE AFTER civil_status;
