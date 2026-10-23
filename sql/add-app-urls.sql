-- Run once on a database that already has APP VMs.
-- Copies each existing URL into the new list, then leaves app_vms.url in place.

CREATE TABLE IF NOT EXISTS app_vm_urls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  app_vm_id INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_app_vm_urls_vm (app_vm_id, sort_order),
  CONSTRAINT fk_app_vm_urls_vm FOREIGN KEY (app_vm_id) REFERENCES app_vms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_vm_urls (app_vm_id, url, sort_order)
SELECT v.id, v.url, 0
FROM app_vms v
WHERE v.url IS NOT NULL
  AND v.url <> ''
  AND NOT EXISTS (
    SELECT 1 FROM app_vm_urls u WHERE u.app_vm_id = v.id AND u.url = v.url
  );
