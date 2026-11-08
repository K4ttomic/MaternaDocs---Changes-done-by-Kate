CREATE DATABASE IF NOT EXISTS maternadocs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maternadocs;

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(30)  NOT NULL UNIQUE,   -- lowercase letters, digits, . and _
  password_hash   VARCHAR(255) NOT NULL,
  first_name      VARCHAR(80)  NOT NULL,
  middle_name     VARCHAR(80)  NULL,
  last_name       VARCHAR(80)  NOT NULL,
  role            ENUM('admin','front_desk','patient') NOT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  last_login_at   DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Stable core fields only. Form-specific fields get added by migration once templates arrive.
CREATE TABLE IF NOT EXISTS patients (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id                INT UNSIGNED NULL UNIQUE,   -- links a patient to her portal login
  first_name             VARCHAR(80) NOT NULL,
  middle_name            VARCHAR(80) NULL,
  last_name              VARCHAR(80) NOT NULL,
  birthdate              DATE NOT NULL,
  civil_status           ENUM('single','married','widowed','separated','other') NULL,
  -- >>> CONTINUED WORK (2026-10-30): column below was added <<<
  philhealth_no          VARCHAR(14) NULL UNIQUE,   -- stored as XX-XXXXXXXXX-X
  -- >>> end of added column <<<
  -- >>> CONTINUED WORK (2026-11-06): Health Record template fields <<<
  family_serial_no       VARCHAR(30)   NULL,
  blood_type             ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NULL,
  education_level        ENUM('No formal education','Elementary','High School','Vocational/Technical','College','Post-Graduate','Unknown') NULL,
  occupation             VARCHAR(100)  NULL,
  spouse_first_name      VARCHAR(80)   NULL,
  spouse_middle_name     VARCHAR(80)   NULL,
  spouse_last_name       VARCHAR(80)   NULL,
  spouse_blood_type      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NULL,
  spouse_education_level ENUM('No formal education','Elementary','High School','Vocational/Technical','College','Post-Graduate','Unknown') NULL,
  spouse_occupation      VARCHAR(100)  NULL,
  philhealth_membership  ENUM('Member','Dependent','Indigent','None','Unknown') NULL,
  weight_kg              DECIMAL(5,2)  NULL,
  height_cm              DECIMAL(5,2)  NULL,
  -- >>> end of Health Record fields <<<
  address                VARCHAR(255) NULL,
  contact_no             VARCHAR(20) NULL,
  lmp                    DATE NULL,
  edd                    DATE NULL,
  gravida                TINYINT UNSIGNED NULL,
  created_by             INT UNSIGNED NULL,
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (last_name, first_name),
  CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_pat_by   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  detail     VARCHAR(255) NULL,
  ip         VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id), INDEX idx_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
