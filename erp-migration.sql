-- ============================================================
-- ERP foundation migration: couriers, shipments, COD, expenses,
-- suppliers, purchases, returns, notifications, order timeline.
-- Run once via phpMyAdmin (SQL tab). Safe to re-run (IF NOT EXISTS).
-- ============================================================

-- ---------- couriers ----------
CREATE TABLE IF NOT EXISTS couriers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL DEFAULT '',
  logo VARCHAR(255) NOT NULL DEFAULT '',
  account_no VARCHAR(100) NOT NULL DEFAULT '',
  customer_id VARCHAR(100) NOT NULL DEFAULT '',
  contact_person VARCHAR(100) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(150) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  api_base_url VARCHAR(255) NOT NULL DEFAULT '',
  api_key VARCHAR(255) NOT NULL DEFAULT '',
  api_secret VARCHAR(255) NOT NULL DEFAULT '',
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  shipping_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  return_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  settlement_days INT NOT NULL DEFAULT 14,
  service_areas TEXT,
  tracking_url VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- shipments ----------
CREATE TABLE IF NOT EXISTS shipments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  courier_id INT UNSIGNED NOT NULL DEFAULT 0,
  tracking_no VARCHAR(100) NOT NULL DEFAULT '',
  booking_ref VARCHAR(100) NOT NULL DEFAULT '',
  service_type VARCHAR(50) NOT NULL DEFAULT 'overnight',
  pieces INT NOT NULL DEFAULT 1,
  weight DECIMAL(8,2) NOT NULL DEFAULT 0.50,
  cod_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('booked','dispatched','in_transit','delivered','returned','cancelled') NOT NULL DEFAULT 'booked',
  booked_at TIMESTAMP NULL DEFAULT NULL,
  dispatched_at TIMESTAMP NULL DEFAULT NULL,
  delivered_at TIMESTAMP NULL DEFAULT NULL,
  notes TEXT,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order (order_id),
  KEY idx_courier (courier_id),
  KEY idx_tracking (tracking_no),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- shipment status history ----------
CREATE TABLE IF NOT EXISTS shipment_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shipment_id INT UNSIGNED NOT NULL,
  status VARCHAR(50) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shipment (shipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- order timeline ----------
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

-- ---------- COD settlements ----------
CREATE TABLE IF NOT EXISTS cod_settlements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  settlement_no VARCHAR(50) NOT NULL DEFAULT '',
  courier_id INT UNSIGNED NOT NULL,
  statement_date DATE NOT NULL,
  gross_cod DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
  return_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_expected DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
  difference DECIMAL(12,2) NOT NULL DEFAULT 0,
  bank_ref VARCHAR(100) NOT NULL DEFAULT '',
  received_at DATE NULL DEFAULT NULL,
  status ENUM('pending','partial','received','reconciled','disputed') NOT NULL DEFAULT 'pending',
  notes TEXT,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settlement_no (settlement_no),
  KEY idx_courier (courier_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- settlement lines (per tracking no) ----------
CREATE TABLE IF NOT EXISTS settlement_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  settlement_id INT UNSIGNED NOT NULL,
  shipment_id INT UNSIGNED NOT NULL DEFAULT 0,
  tracking_no VARCHAR(100) NOT NULL DEFAULT '',
  cod_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
  net DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_settlement (settlement_id),
  KEY idx_tracking (tracking_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- courier ledger ----------
CREATE TABLE IF NOT EXISTS courier_ledger (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  courier_id INT UNSIGNED NOT NULL,
  entry_date DATE NOT NULL,
  type ENUM('cod_due','collected','settlement','shipping_fee','return_fee','adjustment','dispute') NOT NULL,
  ref_type VARCHAR(30) NOT NULL DEFAULT '',
  ref_id INT UNSIGNED NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL DEFAULT '',
  debit DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_courier_date (courier_id, entry_date),
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- expense categories ----------
CREATE TABLE IF NOT EXISTS expense_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO expense_categories (id, name) VALUES
  (1, 'Shipping'), (2, 'Packaging'), (3, 'Marketing / Ads'),
  (4, 'Salaries'), (5, 'Rent & Utilities'), (6, 'Returns'),
  (7, 'Courier Deductions'), (8, 'Other');

-- ---------- expenses ----------
CREATE TABLE IF NOT EXISTS expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  expense_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL DEFAULT 8,
  title VARCHAR(200) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
  notes TEXT,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_date_cat (expense_date, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- suppliers ----------
CREATE TABLE IF NOT EXISTS suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  company VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(150) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(80) NOT NULL DEFAULT '',
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- purchases ----------
CREATE TABLE IF NOT EXISTS purchases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_no VARCHAR(50) NOT NULL DEFAULT '',
  supplier_id INT UNSIGNED NOT NULL,
  purchase_date DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','partial','paid','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_no (po_no),
  KEY idx_supplier (supplier_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- purchase items ----------
CREATE TABLE IF NOT EXISTS purchase_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL DEFAULT 0,
  product_name VARCHAR(200) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_purchase (purchase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- stock adjustments ----------
CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('in','out','damage','return_in') NOT NULL,
  qty INT NOT NULL,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  ref_type VARCHAR(30) NOT NULL DEFAULT '',
  ref_id INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- returns / RMA ----------
CREATE TABLE IF NOT EXISTS returns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rma_no VARCHAR(50) NOT NULL DEFAULT '',
  order_id INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('requested','approved','shipped','received','inspected','restocked','refunded','rejected') NOT NULL DEFAULT 'requested',
  return_tracking_no VARCHAR(100) NOT NULL DEFAULT '',
  refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  restocked TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT,
  created_by INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rma_no (rma_no),
  KEY idx_order (order_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- in-system notifications ----------
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(50) NOT NULL DEFAULT 'info',
  title VARCHAR(200) NOT NULL,
  message TEXT,
  link VARCHAR(255) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- seed: popular Pakistani couriers (manual mode) ----------
INSERT IGNORE INTO couriers (id, name, slug, tracking_url, status, sort_order) VALUES
  (1, 'TCS', 'tcs', 'https://www.tcsexpress.com/track/', 'active', 1),
  (2, 'Leopards Courier', 'leopards', 'https://www.leopardscourier.com/tracking', 'active', 2),
  (3, 'Trax', 'trax', 'https://trax.pk/track/', 'active', 3),
  (4, 'M&P', 'mp', 'https://www.mulphilog.com/track', 'active', 4),
  (5, 'Pakistan Post', 'pakistan-post', 'https://ep.gov.pk/', 'active', 5);
