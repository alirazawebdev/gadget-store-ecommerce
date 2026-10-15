-- Mobile banner support for hero sliders.
-- Run ONCE via phpMyAdmin (SQL tab).
ALTER TABLE sliders ADD COLUMN mobile_image VARCHAR(255) DEFAULT '' AFTER image;
