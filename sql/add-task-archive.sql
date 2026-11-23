-- Run once if Tasks was already installed without an archive date.
-- Skip this file on a brand-new database. sql/add-tasks.sql already includes the column.
-- Do not import sql/schema.sql again.

ALTER TABLE tasks
  ADD COLUMN archived_at DATETIME NULL AFTER updated_at,
  ADD KEY idx_tasks_archived (archived_at, id);
