# BUILD SPEC — Gadget Store (custom PHP + MySQL)

You are building part of a complete e-commerce system. Root: `~/workspace/gadget-store/`
Stack: plain PHP 7.4+ (no framework), MySQL (PDO), HTML/CSS/JS. UI language: English.

## Files already written (DO NOT rewrite, just use them)
- `config/config.php` — DB constants, `UPLOAD_DIR`, `UPLOAD_URL`, `ITEMS_PER_PAGE`, `CURRENCY`
- `config/database.php` — `db(): PDO` singleton
- `includes/functions.php` — ALL helpers (session already started here; never call `session_start()` again)
- `includes/header.php` / `includes/footer.php` — frontend layout. Set `$pageTitle`, `$pageDesc`, `$pageKeys` BEFORE including header.
- `assets/css/style.css`, `assets/js/main.js` — frontend theme + interactions
- `assets/css/admin.css` — admin theme
- `admin/includes/auth.php` — include FIRST in every admin page (except login.php). It loads functions, enforces login, refreshes `$admin` (current user row + `role_name` + `permissions` JSON). Sets `$currentPage`.
- `admin/includes/header.php` / `admin/includes/footer.php` — admin layout + sidebar (permission-aware). Include after auth.php.
- `database.sql`, `install.php`, `.htaccess`, `README.md`

## Helper functions available (includes/functions.php)
- `e($s)` — escape output. Use on EVERYTHING printed from DB/user input (except intentional raw HTML fields: page content, popup content, tracking codes).
- `base_url($path='')`, `admin_url($path='')`
- `setting($key, $default='')`, `site_name()`, `format_price($n)` → "Rs. 1,250"
- `redirect($url)`, `flash($type,$msg)` ($type = success|error), `show_flash()`
- `slugify($t)`, `unique_slug($table,$base,$ignoreId=null)`
- `is_admin()`, `require_admin()`, `current_user()`, `can($perm)`, `log_activity($action)`
- `PERMISSIONS` const: dashboard, orders, products, categories, customers, sliders, popups, coupons, reviews, pages, settings, users, reports, messages
- `upload_image($file,$subdir)` → relative path like `products/abc.jpg` or null. `$subdir` e.g. 'products','sliders','categories','popups','banners'
- `img_url($path)` → full URL or placeholder
- `product_url($p)`, `category_url($c)`, `sale_price($p)`, `discount_percent($p)`
- `product_images($pid)` → array of paths; `product_thumb($p)` → first image URL or placeholder
- Cart (session): `cart_add($pid,$qty)`, `cart_set($pid,$qty)`, `cart_remove($pid)`, `cart_clear()`, `cart_count()`, `cart_items()` (each item has qty, unit_price, line_total), `cart_subtotal()`, `coupon_discount($subtotal)`
- `order_statuses()` → assoc array; `status_badge($s)` → HTML badge
- `pagination($total,$perPage,$page,$baseUrl)` → HTML

## Database tables (exact columns)
- `roles`(id, name, permissions TEXT, created_at)
- `users`(id, role_id, name, email UNIQUE, password, status ENUM active/inactive, created_at)
- `categories`(id, name, slug UNIQUE, image, description TEXT, sort_order INT, status ENUM active/inactive, meta_title, meta_description, meta_keywords, created_at)
- `products`(id, category_id, name, slug UNIQUE, sku, description TEXT, price DECIMAL, sale_price DECIMAL, purchase_price DECIMAL, stock INT, low_stock_alert INT DEFAULT 5, featured TINYINT, is_new TINYINT, on_sale TINYINT, flash_sale TINYINT, flash_ends_at DATETIME NULL, status ENUM active/inactive, meta_title, meta_description, meta_keywords, views INT, created_at)
- `product_images`(id, product_id, image, sort_order)
- `sliders`(id, title, subtitle, image, link, sort_order, status ENUM active/inactive)
- `popups`(id, title, image, content TEXT, button_text, button_link, status ENUM)
- `coupons`(id, code UNIQUE, type ENUM percent/fixed, value DECIMAL, min_order DECIMAL, usage_limit INT, used_count INT, expires_at DATE NULL, status ENUM)
- `customers`(id, name, phone, email, address TEXT, city, total_orders INT, total_spent DECIMAL, created_at)
- `orders`(id, order_no UNIQUE, customer_id, name, phone, email, address TEXT, city, notes TEXT, subtotal DECIMAL, discount DECIMAL, shipping DECIMAL, total DECIMAL, coupon_code, payment_method DEFAULT 'cod', status ENUM pending/confirmed/processing/dispatched/delivered/cancelled/returned, created_at, updated_at)
- `order_items`(id, order_id, product_id, product_name, price DECIMAL, qty, total DECIMAL)
- `pages`(id, title, slug UNIQUE, content MEDIUMTEXT (raw HTML allowed), meta_title, meta_description, meta_keywords, status ENUM, created_at, updated_at)
- `settings`(id, skey UNIQUE, svalue TEXT)
- `reviews`(id, product_id, name, rating TINYINT, comment TEXT, status ENUM pending/approved/rejected, created_at)
- `stock_log`(id, product_id, change_qty INT, type ENUM in/out/adjust, note, created_by, created_at)
- `activity_log`(id, user_id, action, created_at)
- `messages`(id, name, phone, email, message TEXT, status ENUM new/read, created_at)

Settings keys in use: site_name, site_title, meta_description, meta_keywords, announcement_text, currency, delivery_charge, free_shipping_above, whatsapp_number, facebook_url, instagram_url, youtube_url, contact_email, contact_phone, contact_address, ga_code, gsc_code, fb_pixel, site_logo

## Conventions (STRICT)
- Every DB query uses prepared statements with `?` placeholders. Never interpolate variables into SQL (except table name in `unique_slug`, internal only).
- Forms: POST → validate → `flash()` → `redirect()` (POST-redirect-GET). Show `show_flash()` at top of content.
- Admin pages: `require_once __DIR__.'/includes/auth.php';` then check `can('orders')` etc. with graceful "no permission" message; then header.php, content, footer.php.
- Frontend pages: `require_once __DIR__.'/includes/functions.php';` set `$pageTitle` etc., include `includes/header.php`, content, `includes/footer.php`.
- File layout for admin content: `<div class="page-head"><h1>..</h1><a class="btn" ...>..</a></div>` then `.card` blocks. Tables use `<table class="table">`. Forms use `.form-group`, `.form-control`, `.btn`, `.btn-danger`, `.btn-sm`.
- Product card HTML (frontend) — reuse this exact markup everywhere:
  ```html
  <div class="product-card">
    <a href="PRODUCT_URL"><img src="THUMB" alt="NAME"></a>
    [if discount] <span class="sale-badge">-X%</span> [/if]
    <div class="p-info">
      <h3><a href="PRODUCT_URL">NAME</a></h3>
      <div class="p-price"><span class="old">Rs. OLD</span> <span class="new">Rs. NEW</span></div>
      <form class="add-cart-form" method="post" action="ajax/cart.php">
        <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="ID">
        <div class="qty-step"><button type="button" class="qty-minus">−</button><input type="number" name="qty" value="1" min="1"><button type="button" class="qty-plus">+</button></div>
        <button class="btn btn-add" type="submit">Add to Cart</button>
      </form>
    </div>
  </div>
  ```
  (main.js AJAX-intercepts `.add-cart-form` and updates `.cart-count`.)
- Keep PHP 7.4 compatible. No composer packages. Chart.js via CDN allowed in admin.

## Business rules (implement exactly)
- Checkout: find-or-create customer by phone; insert order status 'pending'; order_no = 'QG-'.(1000+id) after insert; insert order_items (price = sale_price at time); if coupon in session and valid → discount, increment coupons.used_count; shipping = setting delivery_charge unless subtotal >= free_shipping_above (0 disables); clear cart+coupon; redirect to order-success.php?o=ORDER_NO.
- Stock decrements ONLY in admin/order-view.php when status changes TO 'confirmed' from 'pending' (loop items: `UPDATE products SET stock = stock - qty`; insert stock_log type 'out', note 'Order QG-xxxx confirmed'). If status changes TO 'cancelled'/'returned' FROM confirmed/processing/dispatched → increment back, stock_log 'in'.
- Product page view: `UPDATE products SET views = views + 1`.
- Reviews: insert with status 'pending'.
- Contact form (on page slug 'contact-us'): insert into messages status 'new'.
- Sitemap: valid XML with URLs for home, all active products, categories, pages.

## Your task
Write ONLY the files assigned to you, under `~/workspace/gadget-store/`. After writing each file, re-read it fully and check PHP syntax mentally (no php binary here). Report the list of files you created.
