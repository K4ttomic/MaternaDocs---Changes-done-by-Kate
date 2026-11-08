-- Migration: Add Health Record fields for Health_Record.pdf template
-- Run once in phpMyAdmin (SQL tab) on existing databases.
-- New columns: family_serial_no, blood_type, education_level, spouse_name,
--   spouse_blood_type, spouse_education_level, spouse_occupation,
--   occupation, philhealth_membership_type, weight_kg, height_cm, age_yrs

USE maternadocs;

ALTER TABLE patients
  ADD COLUMN family_serial_no       VARCHAR(30)   NULL        AFTER philhealth_no,
  ADD COLUMN blood_type             ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NULL AFTER family_serial_no,
  ADD COLUMN education_level        ENUM('No formal education','Elementary','High School','Vocational/Technical','College','Post-Graduate','Unknown') NULL AFTER blood_type,
  ADD COLUMN occupation             VARCHAR(100)  NULL        AFTER education_level,
  ADD COLUMN spouse_first_name      VARCHAR(80)   NULL        AFTER occupation,
  ADD COLUMN spouse_middle_name     VARCHAR(80)   NULL        AFTER spouse_first_name,
  ADD COLUMN spouse_last_name       VARCHAR(80)   NULL        AFTER spouse_middle_name,
  ADD COLUMN spouse_blood_type      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NULL AFTER spouse_last_name,
  ADD COLUMN spouse_education_level ENUM('No formal education','Elementary','High School','Vocational/Technical','College','Post-Graduate','Unknown') NULL AFTER spouse_blood_type,
  ADD COLUMN spouse_occupation      VARCHAR(100)  NULL        AFTER spouse_education_level,
  ADD COLUMN philhealth_membership  ENUM('Member','Dependent','Indigent','None','Unknown') NULL AFTER spouse_occupation,
  ADD COLUMN weight_kg              DECIMAL(5,2)  NULL        AFTER philhealth_membership,
  ADD COLUMN height_cm              DECIMAL(5,2)  NULL        AFTER weight_kg;

