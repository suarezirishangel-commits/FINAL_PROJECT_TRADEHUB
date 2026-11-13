# TradeHub — Local Product Marketplace

A responsive PHP/MySQL business system for a startup local marketplace. Buyers browse and
search local products with live stock and prices; administrators manage listings, users, and
sales, while optional WordPress integration provides headless content management.

## Stack

- PHP 8.2 (tested on WAMP, PHP 8.2.29) — procedural PHP with PDO prepared statements
- MySQL 8 / MariaDB (WAMP)
- PHPMailer 7 (vendored in `vendor/`) for Gmail SMTP order confirmations
- Plain HTML/CSS/JS front end (no build step)

## Setup (WAMP)

1. The project lives at `C:\wamp64\www\FINAL_PROJECT_TRADEHUB`, so open
   <http://localhost/FINAL_PROJECT_TRADEHUB/>.
2. Start WAMP (Apache + MySQL).
3. Import the database — either in phpMyAdmin (`sql/schema.sql`, then `sql/seed.sql`) or:
   ```bash
   mysql -u root < sql/schema.sql
   mysql -u root < sql/seed.sql
   ```
4. Adjust database credentials in `config/config.php` if your MySQL user is not `root`
   with an empty password. Environment variables (`TRADEHUB_DB_*`) also work.

Demo accounts:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@tradehub.test | Admin@123 |
| Customer | customer@tradehub.test | Customer@123 |

> Change these before deploying.

## Features

**Storefront**
- Home page with business overview, service highlights, announcements, and featured products.
- Product catalog with full-text search (`q`), category filter, min/max price filter,
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
3. Set `TRADEHUB_WP_URL` to the WordPress site URL (without a trailing slash), for example
   `http://localhost/wordpress`, in the environment available to Apache/PHP. Restart WAMP.
4. In WordPress, open **Settings → TradeHub Content** to edit storefront copy. Create and
   publish items under **Announcements**.
5. Verify the public endpoint at
   `http://localhost/wordpress/wp-json/tradehub/v1/content`, then open the TradeHub storefront.

When WordPress is configured, the storefront reads its public CMS content from this endpoint,
and the TradeHub admin content page links to the WordPress editor. If the endpoint is temporarily
unavailable, TradeHub logs the error in the PHP error log and uses its local content as a fallback.

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

Checkout calls `send_order_confirmation()`. Configure SMTP in `config/config.php`
or via environment variables, then restart Apache:

```
TRADEHUB_SMTP_USER=your.address@gmail.com
TRADEHUB_SMTP_PASS=your-16-character-app-password   # Google App Password, not your login password
TRADEHUB_MAIL_FROM=your.address@gmail.com
```

Defaults: `smtp.gmail.com:587`, STARTTLS. Until credentials are set, messages are written to
`logs/mail.log` so the flow stays testable offline. `logs/` is blocked from web access.

## Security

- Passwords hashed with `password_hash(..., PASSWORD_BCRYPT)`; rehashed on login when needed.
- PDO prepared statements everywhere (no string-built SQL with user input).
- CSRF tokens on every POST form; tokens compared with `hash_equals`.
- Session hardening: HttpOnly + SameSite=Lax cookies, ID regeneration on login and every 30 min.
- Role-based authorization (`require_admin()`), plus server-side ownership scoping for orders.
- Input validation on email, phone, password strength, price, stock, and enums.
- All output escaped with `htmlspecialchars`; login throttled after 5 failed attempts.

## Notes

- Product images are stylised gradient tiles generated from the product name, so the app works
  fully offline without external image hosting.
