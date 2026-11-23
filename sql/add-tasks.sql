-- Run once on a database that is already in use.
-- Do this before pulling the code that adds Tasks.
-- Do not import sql/schema.sql again.

CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archived_at DATETIME NULL,
  completion_notified_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_tasks_created_by (created_by, updated_at),
  KEY idx_tasks_archived (archived_at, id),
  CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id INT UNSIGNED NOT NULL,
  vm_side ENUM('app', 'db') NOT NULL,
  vm_id INT UNSIGNED NOT NULL,
  assigned_user_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_task_vm (task_id, vm_side, vm_id),
  KEY idx_task_items_user (assigned_user_id, task_id),
  CONSTRAINT fk_task_items_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  CONSTRAINT fk_task_items_user FOREIGN KEY (assigned_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_user_status (
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (task_id, user_id),
  CONSTRAINT fk_task_status_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
  CONSTRAINT fk_task_status_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
