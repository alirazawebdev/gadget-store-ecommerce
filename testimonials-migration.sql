-- ============================================================
--  Customer Testimonials + extra social links
--  Run this in phpMyAdmin > your database > SQL tab > Go
--  (Safe to run: uses IF NOT EXISTS / INSERT IGNORE)
-- ============================================================

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

INSERT IGNORE INTO settings (skey, svalue) VALUES
('tiktok_url', '#'),
('whatsapp_url', '#');
