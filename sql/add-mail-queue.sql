-- Run once on a database that already has Tasks.
-- Do not import sql/schema.sql again.

ALTER TABLE tasks
  ADD COLUMN completion_notified_at DATETIME NULL AFTER archived_at;

CREATE TABLE IF NOT EXISTS mail_outbox (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient VARCHAR(254) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_mail_outbox_pending (sent_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
