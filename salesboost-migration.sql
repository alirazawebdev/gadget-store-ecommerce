-- ============================================================
--  Sales Boost (Shopify-style urgency widgets) — settings
--  Run this in phpMyAdmin > your database > SQL tab > Go
--  (Safe to run: INSERT IGNORE skips keys that already exist)
-- ============================================================

INSERT IGNORE INTO settings (skey, svalue) VALUES
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
('boost_badges_new_days', '14');
