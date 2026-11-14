# TradeHub — Local Product Marketplace

A responsive local marketplace powered by Laravel 12, Blade, and MySQL. Buyers browse and
search local products with live stock and prices; administrators manage listings, users, and
sales, while optional WordPress integration provides headless content management. The
original procedural PHP app remains at the project root as a legacy reference; the Laravel
application is in `laravel-backend/`.

## Stack

- Laravel 12 / PHP 8.2+ (tested on WAMP with PHP 8.3)
- MySQL 8 / MariaDB using the existing `tradehub` schema
- Laravel Blade storefront and admin pages, retaining the existing CSS/JavaScript
- Optional WordPress headless CMS for storefront copy and announcements

## Run the Laravel app (WAMP)

1. Start WAMP and make sure MySQL is running. The app expects the existing database named
   `tradehub`.
2. **Only if the database has not been created yet**, import `sql/schema.sql`, then
   `sql/seed.sql` using phpMyAdmin. `schema.sql` drops and recreates the TradeHub tables, so
   do not re-import it into a database containing data you need.
3. Open PowerShell in `C:\wamp64\www\FINAL_PROJECT_TRADEHUB\laravel-backend`, install
   Composer dependencies if needed, and copy `.env.example` to `.env` only if `.env` does not
   already exist:
   ```powershell
   Set-Location 'C:\wamp64\www\FINAL_PROJECT_TRADEHUB\laravel-backend'
   composer install
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   ```
4. Set `DB_DATABASE=tradehub`, `DB_USERNAME=root`, and your local MySQL password in
   `laravel-backend/.env`. Generate the application key once:
   ```powershell
   C:\wamp64\bin\php\php8.3.28\php.exe artisan key:generate
   ```
5. Start Laravel and open <http://127.0.0.1:8000>:
   ```powershell
   C:\wamp64\bin\php\php8.3.28\php.exe artisan serve
   ```

The Laravel models map directly to the existing legacy tables, including `users.password_hash`.
Do not run `php artisan migrate` against `tradehub`; Laravel’s default migrations are removed
so they cannot conflict with the existing schema.

The original procedural PHP version can still be opened at
<http://localhost/FINAL_PROJECT_TRADEHUB/>. It is separate from the Laravel application.

Demo accounts:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@tradehub.test | Admin@123 |
| Customer | customer@tradehub.test | Customer@123 |

> Change these before deploying.

## Features

**Storefront**
- Home page with business overview, service highlights, announcements, and featured products.
- Product catalog with product-name and description search (`q`), category filter, min/max price filter,
  in-stock filter, four sort orders, and pagination.
- Product detail page with live stock and related items.
- Session cart, quantity validation against stock, and a two-step checkout.
- Order history with status badges, plus profile and password management.

**Admin panel** (`/admin/`, Administrator role only)
- Dashboard: gross sales, commission, order counts, low-stock alerts, recent transactions.
- Products: create, edit, hide/show, delete (products with order history are deactivated).
- Categories: create, edit, delete (blocked while products reference the category).
- Users: view accounts, change role, activate/deactivate.
- Content: configure the WordPress connection and open its content dashboard; when WordPress
  is not configured, the legacy local content editor remains available.
- Sales reports: date-range gross sales, commission, seller payout, average order,
  sales by category, top products, and a transaction log.

## WordPress headless CMS

The storefront can use a separate WordPress installation to manage its site name, tagline,
homepage copy, contact details, and announcements. Product listings, accounts, cart, checkout,
and orders continue to run in TradeHub's PHP/MySQL application.

1. Install WordPress locally (for example, at `http://localhost/wordpress`) and complete its
   setup with a separate database.
2. Copy `wordpress-plugin/tradehub-headless/tradehub-headless.php` into
   `wordpress/wp-content/plugins/tradehub-headless/` and activate **TradeHub Headless CMS**
   in the WordPress Plugins screen.
3. For the Laravel app, set `TRADEHUB_WP_URL=http://localhost/wordpress` (without a trailing
   slash) in `laravel-backend/.env`, then restart `artisan serve` if it is already running.
   The legacy PHP app reads this setting from the environment available to Apache/PHP.
4. In WordPress, open **Settings → TradeHub Content** to edit storefront copy. Create and
   publish items under **Announcements**.
5. Verify the public endpoint at
   `http://localhost/wordpress/wp-json/tradehub/v1/content`, then open the TradeHub storefront.

When WordPress is configured, the Laravel storefront reads its public CMS content from this
endpoint, and the TradeHub admin content page links to the WordPress editor. If the endpoint is
temporarily unavailable, Laravel logs the error and uses local content as a fallback.

## Database

Database `tradehub`, seven relational tables (five required plus local fallback content tables):

| Table | Purpose |
| --- | --- |
| `roles` | Administrator / Customer |
| `users` | Accounts, bcrypt hashes, profile, status, role FK |
| `categories` | Product classification |
| `products` | Catalog, price, stock, availability, category FK |
| `transactions` | Order lines; rows sharing a `reference` form one order |
| `announcements` | CMS announcements |
| `settings` | CMS site copy + commission rate |

## Email integration (Gmail SMTP)

For the Laravel app, configure SMTP in `laravel-backend/.env`, then restart `artisan serve`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.address@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS=your.address@gmail.com
```

Use a Google App Password, not your normal account password. Without SMTP credentials, checkout
still completes and logs that the confirmation email was not sent.

## Security

- Laravel password hashing and session authentication work with the existing `password_hash`
  column.
- Blade output escaping, CSRF protection, validated inputs, and role-based admin middleware.
- Checkout uses a database transaction and row locks to verify stock and record orders safely.
- Customers can only access their own orders; login attempts are rate limited.

## Notes

- Product images are stylised gradient tiles generated from the product name, so the app works
  fully offline without external image hosting.
