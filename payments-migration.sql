-- ============================================================
-- payments-migration.sql — MANUAL payment methods (Easypaisa / JazzCash /
-- Bank Transfer / COD). The admin enters the RECEIVING account details in
-- Payment Settings; customers transfer from their own app and submit the
-- transaction ID; the admin verifies manually in Orders.
-- Run ONCE in phpMyAdmin BEFORE uploading the payment files.
-- Safe to re-run (all statements are IF NOT EXISTS / guarded).
-- ============================================================

-- 1) New columns on orders -------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_status');
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE orders ADD COLUMN payment_status ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid' AFTER payment_method",
    "SELECT 'payment_status already exists'");
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'transaction_ref');
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE orders ADD COLUMN transaction_ref VARCHAR(100) NOT NULL DEFAULT '' AFTER payment_status",
    "SELECT 'transaction_ref already exists'");
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cod_fee');
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE orders ADD COLUMN cod_fee DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER transaction_ref",
    "SELECT 'cod_fee already exists'");
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'wallet_number');
SET @ddl := IF(@col_exists = 0,
    "ALTER TABLE orders ADD COLUMN wallet_number VARCHAR(30) NOT NULL DEFAULT '' AFTER cod_fee",
    "SELECT 'wallet_number already exists'");
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing COD orders placed before this migration stay unpaid (correct):
UPDATE orders SET payment_status = 'unpaid' WHERE payment_method = 'cod' AND payment_status = 'unpaid';

-- 2) Payment event log (every gateway callback / status check lands here)
CREATE TABLE IF NOT EXISTS payment_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  provider VARCHAR(20) NOT NULL DEFAULT '',
  event_type VARCHAR(40) NOT NULL DEFAULT '',
  payload TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id)
);

-- 3) Settings seeds (all OFF except COD; details filled by admin in Payment Settings)
INSERT IGNORE INTO settings (skey, svalue) VALUES
('pay_cod_enabled',        '1'),
('pay_cod_fee',            '0'),
('pay_easypaisa_enabled',  '0'),
('pay_easypaisa_title',    ''),
('pay_easypaisa_number',   ''),
('pay_easypaisa_whatsapp', ''),
('pay_jazzcash_enabled',   '0'),
('pay_jazzcash_title',     ''),
('pay_jazzcash_number',    ''),
('pay_jazzcash_whatsapp',  ''),
('pay_bank_enabled',       '0'),
('pay_bank_name',          ''),
('pay_bank_title',         ''),
('pay_bank_number',        ''),
('pay_bank_whatsapp',      ''),
('pay_jazzcash_merchant_id',''),
('pay_jazzcash_password',  ''),
('pay_jazzcash_salt',      ''),
('pay_safepay_public_key', ''),
('pay_safepay_secret_key', ''),
('pay_safepay_webhook_secret', ''),
('pay_safepay_intent', 'CYBERSOURCE');
