-- PT Inventory Manager
-- Import into an empty database:
--   CREATE DATABASE pt_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root -p pt_inventory < sql/schema.sql
--
-- The first visit to the site creates the initial admin account.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'ops', 'view') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lookups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind ENUM('client', 'environment', 'application', 'os', 'zone') NOT NULL,
  name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lookups_kind_name (kind, name),
  KEY idx_lookups_kind (kind, active, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stacks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stacks_zone_name (zone_id, name),
  CONSTRAINT fk_stack_zone FOREIGN KEY (zone_id) REFERENCES lookups (id) ON DELETE CASCADE,
  CONSTRAINT fk_stack_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_vms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  environment_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NOT NULL,
  stack_id INT UNSIGNED NULL,
  os_id INT UNSIGNED NOT NULL,
  internal_ip VARCHAR(45) NOT NULL,
  external_ip VARCHAR(45) NULL,
  url VARCHAR(500) NULL,
  brotherhood_url VARCHAR(500) NULL,
  status_page_url VARCHAR(500) NULL,
  notes MEDIUMTEXT NULL,
  troubleshooting MEDIUMTEXT NULL,
  lifecycle ENUM('active', 'decommissioned') NOT NULL DEFAULT 'active',
  decommissioned_at DATETIME NULL,
  decommissioned_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_app_lifecycle_name (lifecycle, name),
  KEY idx_app_client (client_id),
  KEY idx_app_environment (environment_id),
  KEY idx_app_zone (zone_id, lifecycle, name),
  KEY idx_app_stack (stack_id),
  KEY idx_app_os (os_id),
  CONSTRAINT fk_app_client FOREIGN KEY (client_id) REFERENCES lookups (id),
  CONSTRAINT fk_app_environment FOREIGN KEY (environment_id) REFERENCES lookups (id),
  CONSTRAINT fk_app_zone FOREIGN KEY (zone_id) REFERENCES lookups (id),
  CONSTRAINT fk_app_stack FOREIGN KEY (stack_id) REFERENCES stacks (id) ON DELETE SET NULL,
  CONSTRAINT fk_app_os FOREIGN KEY (os_id) REFERENCES lookups (id),
  CONSTRAINT fk_app_decommissioned_by FOREIGN KEY (decommissioned_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_app_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_app_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE db_vms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  environment_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NOT NULL,
  stack_id INT UNSIGNED NULL,
  os_id INT UNSIGNED NOT NULL,
  internal_ip VARCHAR(45) NOT NULL,
  redis TINYINT(1) NOT NULL DEFAULT 0,
  notes MEDIUMTEXT NULL,
  troubleshooting MEDIUMTEXT NULL,
  lifecycle ENUM('active', 'decommissioned') NOT NULL DEFAULT 'active',
  decommissioned_at DATETIME NULL,
  decommissioned_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_db_lifecycle_name (lifecycle, name),
  KEY idx_db_client (client_id),
  KEY idx_db_environment (environment_id),
  KEY idx_db_zone (zone_id, lifecycle, name),
  KEY idx_db_stack (stack_id),
  KEY idx_db_os (os_id),
  KEY idx_db_redis (lifecycle, redis),
  CONSTRAINT fk_db_client FOREIGN KEY (client_id) REFERENCES lookups (id),
  CONSTRAINT fk_db_environment FOREIGN KEY (environment_id) REFERENCES lookups (id),
  CONSTRAINT fk_db_zone FOREIGN KEY (zone_id) REFERENCES lookups (id),
  CONSTRAINT fk_db_stack FOREIGN KEY (stack_id) REFERENCES stacks (id) ON DELETE SET NULL,
  CONSTRAINT fk_db_os FOREIGN KEY (os_id) REFERENCES lookups (id),
  CONSTRAINT fk_db_decommissioned_by FOREIGN KEY (decommissioned_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_db_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_db_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_db_redis CHECK (redis IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_vm_applications (
  app_vm_id INT UNSIGNED NOT NULL,
  application_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (app_vm_id, application_id),
  KEY idx_ava_application (application_id),
  CONSTRAINT fk_ava_vm FOREIGN KEY (app_vm_id) REFERENCES app_vms (id) ON DELETE CASCADE,
  CONSTRAINT fk_ava_application FOREIGN KEY (application_id) REFERENCES lookups (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_db_links (
  app_vm_id INT UNSIGNED NOT NULL,
  db_vm_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (app_vm_id, db_vm_id),
  KEY idx_link_db (db_vm_id),
  CONSTRAINT fk_link_app FOREIGN KEY (app_vm_id) REFERENCES app_vms (id) ON DELETE CASCADE,
  CONSTRAINT fk_link_db FOREIGN KEY (db_vm_id) REFERENCES db_vms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  username VARCHAR(64) NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NULL,
  entity_id INT UNSIGNED NULL,
  summary VARCHAR(500) NOT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_login (action, username, created_at),
  KEY idx_audit_ip (action, ip, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  audit_event_id INT UNSIGNED NOT NULL,
  entity_type ENUM('app_vm', 'db_vm') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  entity_name VARCHAR(190) NOT NULL,
  action ENUM('create', 'update', 'delete', 'decommission', 'restore') NOT NULL,
  field_name VARCHAR(64) NOT NULL,
  old_value MEDIUMTEXT NULL,
  new_value MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hist_entity (entity_type, entity_id, id),
  KEY idx_hist_audit (audit_event_id),
  CONSTRAINT fk_hist_audit FOREIGN KEY (audit_event_id) REFERENCES audit_events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lookups (kind, name, sort_order) VALUES
  ('client', 'PT', 10),
  ('environment', 'prod', 10),
  ('environment', 'test', 20),
  ('environment', 'staging', 30),
  ('application', 'Moodle', 10),
  ('application', 'Drupal', 20),
  ('application', 'LMSX', 30),
  ('os', 'RHEL8', 10),
  ('os', 'RHEL9', 20),
  ('os', 'Rocky9', 30),
  ('os', 'Windows', 40),
  ('zone', 'Z0', 10),
  ('zone', 'Z2', 20),
  ('zone', 'Azure', 30),
  ('zone', 'AWS', 40);
