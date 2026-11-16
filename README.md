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

The platform is designed to help local sellers reach more customers. Administrators manage
product and category listings; the application does not currently provide a separate seller
account role or seller inquiry feature.

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
- Registration, login, profile and password management
- Order history and order status

### Major Modules
- Product and category management
- Online ordering and checkout
- User and role management
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
assignment, and rider GPS tracking.

Open it at <http://localhost/FINAL_PROJECT_TRADEHUB/>.

### Laravel Migration
The Laravel 12 application is in `laravel-backend/` and runs separately from the root PHP
application. It uses Blade and the existing `tradehub` database. The legacy PHP application
remains available while the Laravel version is developed and verified; do not assume every
legacy feature is present in the Laravel application.

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
   once to link the bundled product photos without recreating the tables.
3. Configure the database credentials in `config/config.php` or with the `TRADEHUB_DB_*`
   environment variables. The default database name is `tradehub`.
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
   `laravel-backend/.env`. Generate the application key once:

   ```powershell
   php artisan key:generate
   ```

4. Start the Laravel development server and open <http://127.0.0.1:8000>:

   ```powershell
   php artisan serve
   ```

The Laravel models use the existing TradeHub tables, including `users.password_hash`. Do not
run `php artisan migrate` against `tradehub`; use the project's SQL schema instead.

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
The relational MySQL schema is in `sql/schema.sql`. It defines eight tables:

| Table | Purpose |
| --- | --- |
| `roles` | Administrator, Customer, and Delivery Rider roles |
| `users` | User accounts, password hashes, profiles, and role relation |
| `categories` | Product categories |
| `products` | Product details, prices, stock, and category relation |
| `transactions` | Order lines grouped by order reference |
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
