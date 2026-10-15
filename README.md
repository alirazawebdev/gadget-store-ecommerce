# 🛍️ Gadget Store — Custom PHP + MySQL E-commerce

Complete online store + admin panel (software) built in plain PHP & MySQL. No framework, no composer — runs on any shared hosting (cPanel) or XAMPP.

## What's inside

**Storefront (customer side)**
- Home: announcement marquee, full-screen hero slider, categories, flash sale with countdown, featured / new / on-sale products
- Category & search listing with sorting, product detail with gallery + reviews + related products
- Cart (AJAX), coupon codes, checkout with Cash on Delivery, order tracking page
- Sale popup, floating WhatsApp button, CMS pages (About, Contact, Privacy, Refund, Terms), auto sitemap.xml, per-page SEO meta

**Admin panel (`/admin`)**
- Dashboard: revenue, orders by status, low-stock alerts, 7-day sales chart, top products
- Orders: filter by status, change status (confirming auto-decreases stock), print invoice
- Products: add/edit/delete, multiple images, prices + purchase price, stock, flags, CSV import
- Categories, customers (repeat-customer detection by phone), coupons, sliders, popups, reviews moderation
- Pages CMS (write HTML, publish to site), contact messages inbox
- Settings: site name/logo, delivery charges, WhatsApp, social links
- SEO page: global meta + tracking codes (Google Analytics, Search Console, Facebook Pixel)
- Role-based users (Super Admin / Staff with granular permissions), activity log
- Reports: sales + profit (purchase vs sale price), stock movement log, one-click database backup

## Installation

### Option A — web installer (recommended)
1. Upload the ZIP contents to your hosting (or `htdocs/gadget-store` in XAMPP) and extract.
2. Create an empty MySQL database (cPanel → MySQL Databases) — or let the installer create it if your user has permission.
3. Visit `https://yourdomain.com/install.php`, fill in DB details + admin account, click **Install Now**.
4. **Delete `install.php` and `BUILD_SPEC.md`** from the server.

### Option B — manual
1. Create database `gadget_store`, import `database.sql` via phpMyAdmin.
2. Edit `config/config.php` with your DB credentials.
3. Create the admin user with this SQL (replace name/email and the hash):
   ```sql
   INSERT INTO users (role_id, name, email, password, status)
   VALUES (1, 'Admin', 'admin@example.com', '$2y$10$REPLACE_WITH_YOUR_HASH', 'active');
   ```
   Generate the hash by running once: `php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"`
4. Make `uploads/` writable (755).

## Default URLs
- Store: `/index.php`
- Admin: `/admin/login.php`
- Order tracking: `/track-order.php`
- Sitemap: `/sitemap.php`

## Security notes
- All queries use PDO prepared statements; admin passwords are bcrypt-hashed.
- `uploads/` blocks PHP execution via `.htaccess`.
- Change file permissions and delete `install.php` after setup.

## Customization
- Colors/fonts: `assets/css/style.css` (`:root` variables at the top)
- Homepage sections: `index.php`
- All frontend text (announcement bar, WhatsApp number, delivery charges): Admin → Settings
