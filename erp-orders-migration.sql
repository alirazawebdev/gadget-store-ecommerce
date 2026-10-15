-- ERP Module: order status history (safe to re-run)
-- Run AFTER erp-migration.sql in phpMyAdmin (or run standalone).

CREATE TABLE IF NOT EXISTS order_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(50) NOT NULL DEFAULT '',
  to_status VARCHAR(50) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
