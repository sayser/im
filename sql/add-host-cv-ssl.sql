-- Run once on a database that is already in use.
-- Do not import sql/schema.sql again.

ALTER TABLE users
  ADD COLUMN email VARCHAR(254) NULL AFTER display_name;

ALTER TABLE app_vms
  ADD COLUMN host VARCHAR(190) NULL AFTER external_ip,
  ADD COLUMN cv_backup TINYINT(1) NOT NULL DEFAULT 0 AFTER host,
  ADD CONSTRAINT chk_app_cv_backup CHECK (cv_backup IN (0, 1));

ALTER TABLE db_vms
  ADD COLUMN host VARCHAR(190) NULL AFTER internal_ip,
  ADD COLUMN cv_backup TINYINT(1) NOT NULL DEFAULT 0 AFTER host,
  ADD CONSTRAINT chk_db_cv_backup CHECK (cv_backup IN (0, 1));

CREATE TABLE IF NOT EXISTS ssl_certs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  url VARCHAR(500) NOT NULL,
  expires_on DATE NOT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ssl_expires (expires_on),
  CONSTRAINT fk_ssl_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ssl_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ssl_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ssl_cert_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  lead_amount SMALLINT UNSIGNED NOT NULL,
  lead_unit ENUM('day', 'week') NOT NULL,
  last_notified_for DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ssl_sub_user (ssl_cert_id, user_id),
  KEY idx_ssl_sub_user (user_id),
  CONSTRAINT fk_ssl_sub_cert FOREIGN KEY (ssl_cert_id) REFERENCES ssl_certs (id) ON DELETE CASCADE,
  CONSTRAINT fk_ssl_sub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
