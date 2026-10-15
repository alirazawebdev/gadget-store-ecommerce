-- ============================================================
-- payments-proof-migration.sql — payment screenshot support.
-- Adds orders.payment_proof (path of the uploaded receipt image
-- the customer submits at checkout for Easypaisa / JazzCash /
-- Bank Transfer orders). The admin views it in Orders before
-- marking the payment as paid.
-- Run ONCE in phpMyAdmin BEFORE uploading the updated files.
-- Safe to re-run (guarded with IF NOT EXISTS logic).
-- ============================================================

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_proof');
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE orders ADD COLUMN payment_proof VARCHAR(255) NOT NULL DEFAULT '' AFTER wallet_number",
    "SELECT 'payment_proof already exists'");
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
