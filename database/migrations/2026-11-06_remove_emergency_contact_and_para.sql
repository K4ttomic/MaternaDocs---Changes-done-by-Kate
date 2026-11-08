-- Migration: Remove emergency contact name, emergency contact number, and para from patients
-- Run once in phpMyAdmin (SQL tab) on existing databases.

USE maternadocs;

ALTER TABLE patients
  DROP COLUMN emergency_contact_name,
  DROP COLUMN emergency_contact_no,
  DROP COLUMN para;

