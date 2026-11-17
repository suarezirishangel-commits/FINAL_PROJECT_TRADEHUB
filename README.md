# TradeHub — Local Product Marketplace

TradeHub is a startup-oriented marketplace for local products. It provides product discovery,
online ordering, customer accounts, order management, and CMS integration. The repository
contains the existing PHP application at the project root and a Laravel 12 migration in
`laravel-backend/`; these are separate applications that use the TradeHub MySQL database.

## Business Overview
TradeHub is a web-based local product marketplace that helps customers discover and order
products through one online platform. Administrators manage product listings, categories, user
accounts, orders, and marketplace content. Customers can browse products, check descriptions,
prices, and stock, place orders, and follow order progress.

The platform is designed to help local sellers reach more customers. Administrators assign
products to seller accounts, manage categories, and oversee orders. Customers can review products
and sellers after completed purchases, and users can arrange item-for-item trades.

### Problem
Local sellers may rely on separate social media pages and group posts to promote their products.
Product information can become scattered, difficult to search, or outdated, while customers
may need to visit multiple pages to compare products and prices.

TradeHub addresses this problem by organizing product listings into searchable categories and
providing customers with a single place to browse products, place orders, and follow order
progress. Administrators maintain listings and marketplace information on behalf of sellers.

### Target Users
- Customers who browse and order products
- Local sellers whose products are listed on the marketplace
- Administrators who manage products, orders, users, content, and reports
- Delivery riders who handle assigned deliveries in the legacy PHP application

### Value Proposition and Revenue Model
TradeHub brings product discovery, ordering, and delivery coordination together in one
platform. The business model uses a configurable commission on sales as platform revenue.

## System Components

### User Portal
- Home page and business information
- Product catalog with search, filters, sorting, and pagination
- Product details, live stock, cart, and checkout
- Cash-on-delivery and PayPal checkout (PayPal API credentials required)
- Registration, login, profile and password management
- Order history and order status

### Major Modules
- Product and category management
- Online ordering and checkout
- User and role management
- Product and seller ratings after completed purchases
- Barter listings, offers, and mutual handoff confirmation
- Order and delivery tracking
- Sales reports and commission monitoring

### CMS and System Integration
- WordPress headless CMS for storefront content and announcements
- Gmail SMTP for order-confirmation email
- Leaflet and OpenStreetMap for delivery maps in the legacy PHP application

## Applications in This Repository

### Existing PHP Application
The procedural PHP application at the repository root runs under Apache in WAMP. It includes
the marketplace storefront, administrator pages, order management, cash-on-delivery rider
assignment, rider GPS tracking, and PayPal checkout.

Open it at <http://localhost/FINAL_PROJECT_TRADEHUB/>.

### Laravel Migration
The Laravel 12 application is in `laravel-backend/` and runs separately from the root PHP
application. It uses Blade and the existing `tradehub` database. The legacy PHP application
remains available while the Laravel version is developed and verified; do not assume every
legacy feature is present in the Laravel application.

The Laravel admin Orders page can assign an active Delivery Rider account and set an order to
Out for delivery; customers can see the assigned rider and delivery progress in My orders.
Riders see assigned orders at `/delivery` after signing in. Administrators can set roles from
Users and assign sellers to products from Products. Customers can rate products and sellers
from completed orders. Signed-in users can browse and manage item-for-item barter offers at
`/trades`; both participants must confirm handoff before a trade is complete. PayPal checkout is
available in both applications after sandbox or live credentials and the PayPal table migration
are configured. Ratings and barter are implemented in Laravel only; they are not automatically
available in the separate legacy PHP application.

## Technology
- PHP 8.2+
- Laravel 12 and Blade for the migration application
- MySQL 8 / MariaDB
- WordPress headless CMS plugin
- HTML, CSS, and JavaScript
- PDO prepared statements in the legacy PHP application
- PHPMailer / SMTP for email integration

## Local Setup

### Database and Legacy PHP Application (WAMP)
1. Start WAMP (Apache and MySQL).
2. If the `tradehub` database does not exist, import `sql/schema.sql` and then `sql/seed.sql`
   using phpMyAdmin. **The schema drops and recreates tables. Do not import it into a database
   containing data you need to keep.**
   If the database and seed products already exist, run `sql/migrate_seed_product_images.sql`
   to link the bundled photos and add any missing photo-backed sample categories and products
   (including household, clothing, personal care, and school supplies) without recreating the
   tables. The migration is safe to run again. See `assets/uploads/products/IMAGE_CREDITS.md`
   for third-party photo attribution and licensing. Sample product prices and stock are for
   demonstration only.
   If the existing database predates delivery tracking, also run
   `sql/migrate_live_delivery_tracking.sql`.
   To add seller assignment, purchase ratings, and barter to an existing database, run
   `sql/migrate_ratings_and_trades.sql` once. The existing products remain unassigned until an
   administrator assigns them to active Seller accounts. Register a seller account, change its
   role to Seller under Admin → Users, then assign it to products under Admin → Products.
   Seller ratings are available only for purchases made after the product is assigned.
   To enable server-verified PayPal checkout in both applications, run
   `sql/migrate_paypal_checkout.sql` once. Existing GCash transactions are retained as historical
   records; new checkout no longer offers GCash.
3. Install PHP dependencies from the repository root with `composer install`. Configure the
   database credentials in `config/config.php` or with the `TRADEHUB_DB_*`
   environment variables. To enable PayPal in the legacy PHP app:
   - Sign in at <https://developer.paypal.com/> and open **Apps & Credentials**.
   - Under **Sandbox**, create an app and copy its **Client ID** and **Secret**. Use sandbox
     buyer credentials to test a checkout.
   - Add these as **System variables** in Windows: `TRADEHUB_PAYPAL_MODE=sandbox`,
     `TRADEHUB_PAYPAL_CLIENT_ID=<sandbox client id>`, and
     `TRADEHUB_PAYPAL_CLIENT_SECRET=<sandbox secret>`. Set
     `TRADEHUB_PAYPAL_CURRENCY=PHP` if you want to specify the default explicitly.
   - Restart WAMP/Apache so PHP can read the new environment variables. Do not put the secret
     in a tracked file or share it in chat. PHP cURL must be enabled.
   - Use `TRADEHUB_PAYPAL_MODE=live` with **Live** app credentials only when deploying for real
     payments. Never test with live credentials.
   The checkout shows both PayPal and cash on delivery in the same payment box. PayPal remains
   unavailable until its credentials are configured. The default database name is `tradehub`.
4. Open <http://localhost/FINAL_PROJECT_TRADEHUB/>.

### Laravel Application
1. Start WAMP and confirm the existing `tradehub` database is available.
2. In PowerShell, install dependencies and create `.env` only if it does not already exist:

   ```powershell
   Set-Location 'C:\wamp64\www\FINAL_PROJECT_TRADEHUB\laravel-backend'
   composer install
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   ```

3. Set `DB_DATABASE=tradehub`, `DB_USERNAME=root`, and your local MySQL password in
   `laravel-backend/.env`. Add the same PayPal sandbox settings there:
   `TRADEHUB_PAYPAL_MODE=sandbox`, `TRADEHUB_PAYPAL_CLIENT_ID=<sandbox client id>`,
   `TRADEHUB_PAYPAL_CLIENT_SECRET=<sandbox secret>`, and optionally
   `TRADEHUB_PAYPAL_CURRENCY=PHP`. The Client ID is public; keep the Secret private and never
   commit `.env`. Use the PayPal **Sandbox** app from Apps & Credentials and a sandbox buyer
   account for tests. Use the **Live** Client ID/Secret and `TRADEHUB_PAYPAL_MODE=live` only
   after production deployment. Generate the application key once:

   ```powershell
   php artisan key:generate
   ```

4. Start the Laravel development server and open <http://127.0.0.1:8000>:

   ```powershell
   php artisan serve
   ```

The Laravel models use the existing TradeHub tables, including `users.password_hash`. Do not
run `php artisan migrate` against `tradehub`; use the project's SQL schema instead.

## Render Deployment

The root PHP app and `laravel-backend/` are separate web services. The root `render.yaml`
Blueprint creates one Docker service for each; both are configured to use the same existing
MySQL database and S3-compatible product-image bucket.

1. Make sure the MySQL server is hosted somewhere Render can reach. A database running only on
   your computer/WAMP is not reachable by Render. Do not expose a local MySQL server directly to
   the public internet.
2. Confirm the database has the TradeHub schema before deploying. For a new, empty database,
   import `sql/schema.sql` and then `sql/seed.sql` only if you want its demo users and products.
   **`schema.sql` drops and recreates tables. Never run it against data you need to keep.** For
   an existing database, use only the applicable SQL migrations described in Local Setup. Do not
   run `php artisan migrate`.
3. Create an S3-compatible bucket for product images and credentials restricted to that bucket's
   image prefix (`ListBucket`, `GetObject`, `PutObject`, and `DeleteObject`). Configure public read
   access only for product images (or use a public CDN URL); keep the access key and secret private.
   Both apps must use the same bucket and public base URL. Before deploying, copy any existing
   uploaded product photos that are not in the repository to the bucket, preserving their
   `assets/uploads/products/` object keys. Startup sync seeds the bundled photos only; it cannot
   transfer files from your local computer.
4. In Render, create a new **Blueprint** from this repository and fill the prompted environment
   variables for both services:
   - Root PHP service: `TRADEHUB_DB_HOST`, `TRADEHUB_DB_PORT`, `TRADEHUB_DB_NAME`,
     `TRADEHUB_DB_USER`, `TRADEHUB_DB_PASS`.
   - Laravel service: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
     Use the same database values for both apps.
   - Both services: `AWS_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
     `AWS_DEFAULT_REGION`, `AWS_ENDPOINT`, and `AWS_URL`. `AWS_ENDPOINT` is the S3 API endpoint
     for providers such as Cloudflare R2; it can be blank for AWS S3. `AWS_URL` is the public base
     URL for the bucket/CDN, without a trailing slash. Set `AWS_USE_PATH_STYLE_ENDPOINT` to
     `true` only if your S3-compatible provider requires path-style requests. The app copies the
     bundled product photos to the bucket on startup if they are not already there.
   - Laravel service: generate an application key locally with
     `php artisan key:generate --show` from `laravel-backend/` and enter the result as `APP_KEY`.
     Never commit the key. Laravel uses Render's `RENDER_EXTERNAL_URL` as `APP_URL` when no
     explicit `APP_URL` is configured.
   - If your MySQL provider requires a CA certificate, add it as a Render secret file and set
     `TRADEHUB_DB_SSL_CA` for the root PHP service and `MYSQL_ATTR_SSL_CA` for Laravel to that
     file's path.
5. After deploy, open the two service URLs shown in Render. The root PHP service uses Apache;
   the Laravel service builds its Vite assets during the Docker build. The current Blueprint uses
   Render's free service plan, which may spin down when idle. Change the plans in `render.yaml`
   if you need always-on services.

PayPal and email remain optional. Add their credentials as Render environment variables when
needed. Use live PayPal credentials only for real payments. Product-image storage is shared
through S3; don't replace it with per-service disks, or images uploaded in one app won't be
available to the other.

## WordPress Headless CMS
WordPress can manage storefront content and announcements. The legacy PHP application also
provides a TradeHub administration plugin for marketplace management.

1. Install WordPress locally, for example at `http://localhost/wordpress`.
2. Copy the complete `wordpress-plugin/tradehub-headless/` folder, including `includes/`, into
   `wordpress/wp-content/plugins/`, then activate **TradeHub Headless CMS**.
3. Give the WordPress database account access to the `tradehub` database. For a local
   installation, configure the `TRADEHUB_DB_HOST`, `TRADEHUB_DB_PORT`, `TRADEHUB_DB_NAME`,
   `TRADEHUB_DB_USER`, and `TRADEHUB_DB_PASS` constants in `wp-config.php`.
4. Set `TRADEHUB_WP_URL` to the WordPress URL without a trailing slash. The Laravel application
   reads it from `laravel-backend/.env`; the legacy PHP application reads it from the Apache/PHP
   environment.
5. Verify the content endpoint at
   <http://localhost/wordpress/wp-json/tradehub/v1/content>.

## Database Design
The relational MySQL schema is in `sql/schema.sql`. It defines thirteen tables:

| Table | Purpose |
| --- | --- |
| `roles` | Administrator, Customer, Delivery Rider, and Seller roles |
| `users` | User accounts, password hashes, profiles, and role relation |
| `categories` | Product categories |
| `products` | Product details, prices, stock, category, and assigned seller |
| `transactions` | Order lines grouped by reference, including seller-at-purchase |
| `paypal_payments` | PayPal order/capture state and checkout snapshot for verified payment completion |
| `product_reviews` | Product ratings/comments from customers with completed purchases |
| `seller_reviews` | Seller ratings/comments from customers with completed purchases |
| `trade_listings` | Items offered for zero-cash barter |
| `trade_offers` | Item offers and both parties' handoff confirmations |
| `order_delivery_tracking` | Delivery address, rider assignment, and latest GPS location |
| `announcements` | Local fallback announcements |
| `settings` | Local storefront settings and commission rate |

## Email Integration
The legacy PHP checkout uses Gmail SMTP for order confirmations. Configure SMTP in
`config/config.php` or with `TRADEHUB_SMTP_*` environment variables. Use a Google App Password,
not your regular account password. The Laravel application uses its own mail settings in
`laravel-backend/.env`.

## Security
- Passwords are stored as bcrypt hashes.
- Authentication and role-based authorization protect account and administration features.
- Ratings require a matching completed purchase; seller ratings use the seller recorded at checkout.
- Trade offers are restricted to listing participants, and completion requires both parties' confirmation.
- Forms use CSRF protection and validate user input.
- Database operations use prepared statements or Laravel's database layer.
- Sessions are hardened, and login attempts are rate limited.
- Order and delivery access is restricted to authorized users.

## Demo Accounts

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@tradehub.test` | `Admin@123` |
| Customer | `customer@tradehub.test` | `Customer@123` |

Change demo credentials before deployment.
