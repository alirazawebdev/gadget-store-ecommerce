-- =====================================================
-- Gadget Store — database schema + seed data
-- Import via phpMyAdmin OR run install.php
-- =====================================================
CREATE DATABASE IF NOT EXISTS gadget_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gadget_store;

-- ---------------- roles & users ----------------
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  permissions TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------- catalog ----------------
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  image VARCHAR(255) DEFAULT '',
  description TEXT,
  sort_order INT DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  meta_title VARCHAR(200) DEFAULT '',
  meta_description VARCHAR(300) DEFAULT '',
  meta_keywords VARCHAR(300) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT 0,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  sku VARCHAR(50) DEFAULT '',
  description TEXT,
  price DECIMAL(12,0) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,0) NOT NULL DEFAULT 0,
  purchase_price DECIMAL(12,0) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  low_stock_alert INT NOT NULL DEFAULT 5,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  on_sale TINYINT(1) NOT NULL DEFAULT 0,
  flash_sale TINYINT(1) NOT NULL DEFAULT 0,
  flash_ends_at DATETIME DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  meta_title VARCHAR(200) DEFAULT '',
  meta_description VARCHAR(300) DEFAULT '',
  meta_keywords VARCHAR(300) DEFAULT '',
  views INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category_id),
  INDEX (status)
);

CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  INDEX (product_id)
);

-- ---------------- homepage content ----------------
CREATE TABLE IF NOT EXISTS sliders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) DEFAULT '',
  subtitle VARCHAR(300) DEFAULT '',
  image VARCHAR(255) DEFAULT '',
  mobile_image VARCHAR(255) DEFAULT '',
  link VARCHAR(255) DEFAULT '',
  sort_order INT DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS popups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) DEFAULT '',
  image VARCHAR(255) DEFAULT '',
  content TEXT,
  button_text VARCHAR(100) DEFAULT '',
  button_link VARCHAR(255) DEFAULT '',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(12,0) NOT NULL DEFAULT 0,
  usage_limit INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  expires_at DATE DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

-- ---------------- customers & orders ----------------
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(150) DEFAULT '',
  address TEXT,
  city VARCHAR(100) DEFAULT '',
  total_orders INT NOT NULL DEFAULT 0,
  total_spent DECIMAL(14,0) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (phone)
);

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT DEFAULT 0,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(150) DEFAULT '',
  address TEXT,
  city VARCHAR(100) DEFAULT '',
  notes TEXT,
  subtotal DECIMAL(12,0) NOT NULL DEFAULT 0,
  discount DECIMAL(12,0) NOT NULL DEFAULT 0,
  shipping DECIMAL(12,0) NOT NULL DEFAULT 0,
  total DECIMAL(12,0) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(50) DEFAULT '',
  payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
  status ENUM('pending','confirmed','processing','dispatched','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status),
  INDEX (phone)
);

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT 0,
  product_name VARCHAR(200) NOT NULL,
  price DECIMAL(12,0) NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  total DECIMAL(12,0) NOT NULL DEFAULT 0,
  INDEX (order_id)
);

-- ---------------- cms pages ----------------
CREATE TABLE IF NOT EXISTS pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  content MEDIUMTEXT,
  meta_title VARCHAR(200) DEFAULT '',
  meta_description VARCHAR(300) DEFAULT '',
  meta_keywords VARCHAR(300) DEFAULT '',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------- settings ----------------
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skey VARCHAR(100) NOT NULL UNIQUE,
  svalue TEXT
);

-- ---------------- engagement ----------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  comment TEXT,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id)
);

CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  review_text TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO testimonials (name, rating, review_text, status, sort_order) VALUES
('Ahmed Khan', 5, 'Ordered a smart watch — original product, fast delivery to Lahore in 2 days. Cash on delivery makes it totally risk-free. Highly recommended!', 'active', 1),
('Fatima Raza', 5, 'Best online gadget store in Pakistan. The air buds quality is amazing for this price and customer support on WhatsApp is very helpful.', 'active', 2),
('Bilal Ahmed', 4, 'Bought kitchen gadgets for my home. Packaging was excellent and everything works perfectly. Will definitely order again.', 'active', 3),
('Ayesha Malik', 5, 'I was worried about online shopping but their 7-day return policy gave me confidence. Product arrived exactly as shown in pictures!', 'active', 4);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  email VARCHAR(150) DEFAULT '',
  message TEXT,
  status ENUM('new','read') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------- logs ----------------
CREATE TABLE IF NOT EXISTS stock_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  change_qty INT NOT NULL,
  type ENUM('in','out','adjust') NOT NULL,
  note VARCHAR(255) DEFAULT '',
  created_by INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id)
);

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
);

-- =====================================================
-- SEED DATA
-- =====================================================
INSERT INTO roles (id, name, permissions) VALUES
(1, 'Super Admin', '["all"]'),
(2, 'Staff', '["dashboard","orders","products","categories","customers","messages"]');

INSERT INTO settings (skey, svalue) VALUES
('site_name', 'Gadget Store'),
('site_title', 'Gadget Store - Best Gadgets Online in Pakistan'),
('meta_description', 'Buy quality gadgets online in Pakistan at the best prices with cash on delivery nationwide'),
('meta_keywords', 'gadgets, online shopping pakistan, mobile accessories, kitchen gadgets'),
('announcement_text', 'Cash on Delivery available nationwide - Free delivery on orders above Rs 2000'),
('currency', 'Rs.'),
('delivery_charge', '200'),
('free_shipping_above', '2000'),
('whatsapp_number', '03000000000'),
('facebook_url', '#'),
('instagram_url', '#'),
('youtube_url', '#'),
('tiktok_url', '#'),
('whatsapp_url', '#'),
('boost_lowstock', '1'),
('boost_lowstock_default', '5'),
('boost_viewers', '1'),
('boost_viewers_min', '12'),
('boost_viewers_max', '48'),
('boost_countdown', '1'),
('boost_stockbar', '1'),
('boost_stickybar', '1'),
('boost_salespop', '1'),
('boost_salespop_interval', '15'),
('boost_salespop_duration', '6'),
('boost_badges', '1'),
('boost_badges_new_days', '14'),
('contact_email', 'info@example.com'),
('contact_phone', '0300-0000000'),
('contact_address', 'Pakistan'),
('ga_code', ''),
('gsc_code', ''),
('fb_pixel', ''),
('site_logo', '');

INSERT INTO categories (name, slug, image, description, sort_order, status) VALUES
('Mobile Accessories', 'mobile-accessories', 'categories/mobile.jpg', 'Chargers, earbuds, covers and more', 1, 'active'),
('Kitchen Gadgets', 'kitchen-gadgets', 'categories/kitchen.jpg', 'Smart tools for your kitchen', 2, 'active'),
('Home & Living', 'home-living', 'categories/home.jpg', 'Home improvement and decor', 3, 'active'),
('Beauty & Personal Care', 'beauty-care', 'categories/beauty.jpg', 'Beauty essentials', 4, 'active'),
('Bags & Travel', 'bags-travel', 'categories/travel.jpg', 'Bags, organizers and travel gear', 5, 'active'),
('Kids & Toys', 'kids-toys', 'categories/kids.jpg', 'Fun for little ones', 6, 'active');

INSERT INTO products (category_id, name, slug, sku, description, price, sale_price, purchase_price, stock, featured, is_new, on_sale, status) VALUES
(1, 'Wireless Bluetooth Earbuds Pro', 'wireless-bluetooth-earbuds-pro', 'SKU-001', 'High quality wireless earbuds with charging case and deep bass sound', 2500, 1799, 1100, 50, 1, 1, 1, 'active'),
(2, 'Electric Vegetable Cutter', 'electric-vegetable-cutter', 'SKU-002', 'Fast electric vegetable cutter and chopper for easy cooking', 1800, 1299, 800, 30, 1, 0, 1, 'active'),
(3, 'LED Strip Lights 5 Meter', 'led-strip-lights-5-meter', 'SKU-003', 'RGB LED strip lights with remote for room decoration', 1500, 999, 600, 100, 1, 1, 1, 'active'),
(2, 'Mini Portable Blender', 'mini-portable-blender', 'SKU-004', 'Rechargeable portable blender for shakes and juices', 2200, 1599, 1000, 8, 0, 1, 1, 'active'),
(5, 'Ladies Cross Body Bag', 'ladies-cross-body-bag', 'SKU-005', 'Premium quality cross body bag for women', 2300, 1600, 950, 25, 1, 0, 1, 'active'),
(4, 'Rotating Jewelry Organizer Box', 'rotating-jewelry-organizer-box', 'SKU-006', '4 layer rotating jewelry organizer with mirror', 1200, 850, 500, 40, 0, 0, 1, 'active'),
(1, 'Smart Watch Fit Pro', 'smart-watch-fit-pro', 'SKU-007', 'Smart watch with heart rate monitor and fitness tracking', 3500, 2499, 1600, 20, 1, 1, 0, 'active'),
(3, '2 Layer Kitchen Storage Rack', '2-layer-kitchen-storage-rack', 'SKU-008', 'Multi purpose 2 layer storage rack for kitchen and bathroom', 1450, 1050, 650, 3, 0, 0, 1, 'active');

INSERT INTO sliders (title, subtitle, image, link, sort_order, status) VALUES
('Big Gadget Sale', 'Up to 50 percent off on top gadgets', 'assets/images/banners/banner1.jpg', 'category.php', 1, 'active'),
('New Arrivals', 'Check out the latest gadgets in store', 'assets/images/banners/banner2.jpg', 'category.php', 2, 'active'),
('Best Prices Guaranteed', 'Discount up to 30 percent on accessories', 'assets/images/banners/banner3.jpg', 'category.php', 3, 'active');

INSERT INTO product_images (product_id, image, sort_order) VALUES
(1, 'products/earbuds.jpg', 1),
(2, 'products/chopper.jpg', 1),
(3, 'products/led.jpg', 1),
(4, 'products/blender.jpg', 1),
(5, 'products/bag.jpg', 1),
(6, 'products/jewelry.jpg', 1),
(7, 'products/watch.jpg', 1),
(8, 'products/rack.jpg', 1);

INSERT INTO popups (title, image, content, button_text, button_link, status) VALUES
('Mega Sale Alert', 'assets/images/banners/banner3.jpg', '<p>Flat <strong>30 percent OFF</strong> on all kitchen gadgets this week. Hurry, limited stock.</p>', 'Shop Now', 'category.php?slug=kitchen-gadgets', 'active');

INSERT INTO pages (title, slug, content, status) VALUES
('About Us', 'about-us', '<p>Welcome to our store. We sell quality gadgets at the best prices in Pakistan with cash on delivery nationwide.</p>', 'active'),
('Contact Us', 'contact-us', '<p>Have a question about your order? Send us a message using the form below and we will get back to you soon.</p>', 'active'),
('Privacy Policy', 'privacy-policy', '<p>We respect your privacy. Your personal information is only used to process your orders and is never shared with third parties.</p>', 'active'),
('Refund Policy', 'refund-policy', '<p>If you receive a damaged or wrong item, contact us within 7 days for a replacement or refund.</p>', 'active'),
('Terms and Conditions', 'terms-conditions', '<p>By using this website you agree to our terms. All prices are in PKR. Cash on delivery is available across Pakistan.</p>', 'active');

INSERT INTO coupons (code, type, value, min_order, usage_limit, status) VALUES
('WELCOME10', 'percent', 10, 1000, 0, 'active');
