<?php

if (!defined('ABSPATH')) {
    exit;
}

function tradehub_admin_pages(): array
{
    return [
        'tradehub-dashboard' => 'Dashboard',
        'tradehub-products' => 'Products',
        'tradehub-categories' => 'Categories',
        'tradehub-orders' => 'Orders',
        'tradehub-users' => 'Users',
        'tradehub-reports' => 'Sales reports',
        'tradehub-content' => 'Storefront content',
    ];
}

function tradehub_admin_register_menu(): void
{
    add_menu_page(
        'TradeHub',
        'TradeHub',
        'manage_options',
        'tradehub-dashboard',
        'tradehub_admin_render_dashboard',
        'dashicons-store',
        26
    );

    $callbacks = [
        'tradehub-dashboard' => 'tradehub_admin_render_dashboard',
        'tradehub-products' => 'tradehub_admin_render_products',
        'tradehub-categories' => 'tradehub_admin_render_categories',
        'tradehub-orders' => 'tradehub_admin_render_orders',
        'tradehub-users' => 'tradehub_admin_render_users',
        'tradehub-reports' => 'tradehub_admin_render_reports',
        'tradehub-content' => 'tradehub_admin_render_content',
    ];

    foreach (tradehub_admin_pages() as $slug => $label) {
        add_submenu_page(
            'tradehub-dashboard',
            $label . ' · TradeHub',
            $label,
            'manage_options',
            $slug,
            $callbacks[$slug]
        );
    }

    add_submenu_page(
        'tradehub-dashboard',
        'Announcements · TradeHub',
        'Announcements',
        'manage_options',
        'edit.php?post_type=th_announcement'
    );
}
add_action('admin_menu', 'tradehub_admin_register_menu');

function tradehub_admin_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = defined('TRADEHUB_DB_HOST') ? TRADEHUB_DB_HOST : DB_HOST;
    $port = defined('TRADEHUB_DB_PORT') ? TRADEHUB_DB_PORT : '3306';
    $name = defined('TRADEHUB_DB_NAME') ? TRADEHUB_DB_NAME : 'tradehub';
    $user = defined('TRADEHUB_DB_USER') ? TRADEHUB_DB_USER : DB_USER;
    $pass = defined('TRADEHUB_DB_PASS') ? TRADEHUB_DB_PASS : DB_PASSWORD;

    if (!defined('TRADEHUB_DB_PORT') && preg_match('/^(.+):([0-9]+)$/', (string) $host, $matches)) {
        $host = $matches[1];
        $port = $matches[2];
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function tradehub_admin_url(string $page, array $args = []): string
{
    return add_query_arg($args, admin_url('admin.php?page=' . $page));
}

function tradehub_admin_redirect(string $page, string $message, string $type = 'success'): void
{
    wp_safe_redirect(tradehub_admin_url($page, [
        'th_notice' => $message,
        'th_notice_type' => $type,
    ]));
    exit;
}

function tradehub_admin_notices(): void
{
    if (!isset($_GET['th_notice'])) {
        return;
    }

    $type = isset($_GET['th_notice_type']) && $_GET['th_notice_type'] === 'error' ? 'error' : 'success';
    printf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr($type),
        esc_html(sanitize_text_field(wp_unslash($_GET['th_notice'])))
    );
}
add_action('admin_notices', 'tradehub_admin_notices');

function tradehub_admin_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['tradehub_action'])) {
        return;
    }

    $page = isset($_POST['tradehub_page']) ? sanitize_key(wp_unslash($_POST['tradehub_page'])) : '';
    $action = sanitize_key(wp_unslash($_POST['tradehub_action']));
    if (!isset(tradehub_admin_pages()[$page])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to manage TradeHub.', 'tradehub-headless'), '', ['response' => 403]);
    }
    check_admin_referer('tradehub_' . $action);

    try {
        tradehub_admin_process_action($page, $action);
    } catch (PDOException $error) {
        error_log('TradeHub admin database operation failed: ' . $error->getMessage());
        tradehub_admin_redirect($page, 'The database operation failed. Check the TradeHub database configuration and try again.', 'error');
    } catch (Throwable $error) {
        error_log('TradeHub admin operation failed: ' . $error->getMessage());
        tradehub_admin_redirect($page, 'The request could not be completed. Check the PHP error log for details.', 'error');
    }
}
add_action('admin_init', 'tradehub_admin_handle_post');

function tradehub_admin_process_action(string $page, string $action): void
{
    $pdo = tradehub_admin_db();

    if ($page === 'tradehub-products') {
        tradehub_admin_product_action($pdo, $action);
        return;
    }
    if ($page === 'tradehub-categories') {
        tradehub_admin_category_action($pdo, $action);
        return;
    }
    if ($page === 'tradehub-orders') {
        tradehub_admin_order_action($pdo, $action);
        return;
    }
    if ($page === 'tradehub-users') {
        tradehub_admin_user_action($pdo, $action);
        return;
    }
    if ($page === 'tradehub-content' && $action === 'save_content') {
        $values = [
            'site_name' => sanitize_text_field(wp_unslash($_POST['site_name'] ?? '')),
            'tagline' => sanitize_text_field(wp_unslash($_POST['tagline'] ?? '')),
            'hero_title' => sanitize_text_field(wp_unslash($_POST['hero_title'] ?? '')),
            'hero_text' => sanitize_textarea_field(wp_unslash($_POST['hero_text'] ?? '')),
            'about_text' => sanitize_textarea_field(wp_unslash($_POST['about_text'] ?? '')),
            'contact_email' => sanitize_email(wp_unslash($_POST['contact_email'] ?? '')),
            'contact_phone' => sanitize_text_field(wp_unslash($_POST['contact_phone'] ?? '')),
        ];
        $commission = trim((string) wp_unslash($_POST['commission_rate'] ?? ''));
        if (mb_strlen($values['site_name']) < 2 || !is_email($values['contact_email'])) {
            tradehub_admin_redirect($page, 'Enter a site name and valid contact email.', 'error');
        }
        if (!is_numeric($commission) || (float) $commission < 0 || (float) $commission > 100) {
            tradehub_admin_redirect($page, 'Commission rate must be between 0 and 100.', 'error');
        }

        foreach ($values as $key => $value) {
            update_option('tradehub_' . $key, $value);
        }
        $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute(['commission_rate', $commission]);
        tradehub_admin_redirect($page, 'Storefront content saved.');
    }
}

function tradehub_admin_product_action(PDO $pdo, string $action): void
{
    if ($action === 'save_product') {
        $id = absint($_POST['id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $categoryId = absint($_POST['category_id'] ?? 0);
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $price = trim((string) wp_unslash($_POST['price'] ?? ''));
        $stock = trim((string) wp_unslash($_POST['stock'] ?? ''));
        $active = isset($_POST['is_active']) ? 1 : 0;

        $category = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $category->execute([$categoryId]);
        if (mb_strlen($name) < 3 || !$category->fetchColumn() || !is_numeric($price) || (float) $price < 0 || !ctype_digit($stock)) {
            tradehub_admin_redirect('tradehub-products', 'Check the product name, category, price, and stock values.', 'error');
        }

        $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $current = $id > 0 ? $stmt->fetchColumn() : false;
        if ($id > 0 && $current === false) {
            tradehub_admin_redirect('tradehub-products', 'That product no longer exists.', 'error');
        }

        $image = $current ?: null;
        if (!empty($_FILES['image']['name'])) {
            if ((int) ($_FILES['image']['size'] ?? 0) > 2 * 1024 * 1024) {
                tradehub_admin_redirect('tradehub-products', 'The product photo must be 2MB or smaller.', 'error');
            }
            $imageInfo = @getimagesize((string) ($_FILES['image']['tmp_name'] ?? ''));
            if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                tradehub_admin_redirect('tradehub-products', 'The product photo must be a JPG, PNG, WebP, or GIF image.', 'error');
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachmentId = media_handle_upload('image', 0);
            if (is_wp_error($attachmentId)) {
                tradehub_admin_redirect('tradehub-products', $attachmentId->get_error_message(), 'error');
            }
            $image = wp_get_attachment_url($attachmentId);
        } elseif (isset($_POST['remove_image'])) {
            $image = null;
        }

        if ($id > 0) {
            $pdo->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, image = ?, price = ?, stock = ?, is_active = ? WHERE id = ?')
                ->execute([$categoryId, $name, $description, $image, (float) $price, (int) $stock, $active, $id]);
            tradehub_admin_redirect('tradehub-products', 'Product updated.');
        }
        $pdo->prepare('INSERT INTO products (category_id, name, description, image, price, stock, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$categoryId, $name, $description, $image, (float) $price, (int) $stock, $active]);
        tradehub_admin_redirect('tradehub-products', 'Product added.');
    }

    $id = absint($_POST['id'] ?? 0);
    if ($id < 1) {
        tradehub_admin_redirect('tradehub-products', 'Select a valid product.', 'error');
    }
    if ($action === 'toggle_product') {
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        tradehub_admin_redirect('tradehub-products', 'Product visibility updated.');
    }
    if ($action === 'delete_product') {
        try {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            tradehub_admin_redirect('tradehub-products', 'Product deleted.');
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
            tradehub_admin_redirect('tradehub-products', 'Product has order history and was hidden instead of deleted.', 'error');
        }
    }
}

function tradehub_admin_category_action(PDO $pdo, string $action): void
{
    if ($action === 'save_category') {
        $id = absint($_POST['id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $slugInput = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        $slug = $slugInput !== '' ? $slugInput : sanitize_title($name);
        if (mb_strlen($name) < 2 || $slug === '') {
            tradehub_admin_redirect('tradehub-categories', 'Enter a category name and valid URL slug.', 'error');
        }
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
        $stmt->execute([$slug, $id]);
        if ($stmt->fetchColumn()) {
            tradehub_admin_redirect('tradehub-categories', 'Another category already uses that URL slug.', 'error');
        }
        if ($id > 0) {
            $pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?')
                ->execute([$name, $slug, $description, $id]);
            tradehub_admin_redirect('tradehub-categories', 'Category updated.');
        }
        $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)')->execute([$name, $slug, $description]);
        tradehub_admin_redirect('tradehub-categories', 'Category added.');
    }

    if ($action === 'delete_category') {
        $id = absint($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            tradehub_admin_redirect('tradehub-categories', 'Move or delete the products in this category first.', 'error');
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        tradehub_admin_redirect('tradehub-categories', 'Category deleted.');
    }
}

function tradehub_admin_order_action(PDO $pdo, string $action): void
{
    if ($action !== 'update_order') {
        return;
    }

    $reference = sanitize_text_field(wp_unslash($_POST['reference'] ?? ''));
    $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
    $tracking = sanitize_text_field(wp_unslash($_POST['tracking_number'] ?? ''));
    $riderValue = sanitize_text_field(wp_unslash($_POST['rider_id'] ?? ''));
    $riderId = $riderValue === '' ? null : absint($riderValue);
    $allowed = ['pending', 'paid', 'out_for_delivery', 'completed', 'cancelled'];
    $stmt = $pdo->prepare('SELECT payment_method, paid_at FROM transactions WHERE reference = ? LIMIT 1');
    $stmt->execute([$reference]);
    $order = $stmt->fetch();
    if (!$order || !in_array($status, $allowed, true) || mb_strlen($tracking) > 64) {
        tradehub_admin_redirect('tradehub-orders', 'Check the order status and tracking number.', 'error');
    }

    if ($riderId !== null) {
        $stmt = $pdo->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" AND r.name = "Delivery Rider"');
        $stmt->execute([$riderId]);
        if (!$stmt->fetchColumn() || $order['payment_method'] !== 'cod') {
            tradehub_admin_redirect('tradehub-orders', 'Choose an active delivery rider for a COD order.', 'error');
        }
    }
    if ($status === 'out_for_delivery' && $order['payment_method'] === 'cod' && $riderId === null) {
        tradehub_admin_redirect('tradehub-orders', 'Assign a delivery rider before sending the COD order out.', 'error');
    }
    if ($status === 'completed' && $order['payment_method'] === 'cod' && $order['paid_at'] === null) {
        tradehub_admin_redirect('tradehub-orders', 'Confirm the COD payment before completing the order.', 'error');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE transactions SET status = ?, tracking_number = ?,
             paid_at = IF(? IN ('paid', 'completed'), COALESCE(paid_at, NOW()), paid_at),
             completed_at = IF(? = 'completed', COALESCE(completed_at, NOW()), completed_at),
             cancelled_at = IF(? = 'cancelled', COALESCE(cancelled_at, NOW()), cancelled_at)
             WHERE reference = ?"
        )->execute([$status, $tracking !== '' ? $tracking : null, $status, $status, $status, $reference]);

        $stmt = $pdo->prepare('SELECT rider_id FROM order_delivery_tracking WHERE reference = ? FOR UPDATE');
        $stmt->execute([$reference]);
        $currentRider = $stmt->fetchColumn();
        if ($currentRider === false) {
            if ($riderId !== null) {
                $pdo->prepare('INSERT INTO order_delivery_tracking (reference, rider_id) VALUES (?, ?)')->execute([$reference, $riderId]);
            }
        } elseif ($riderId === null) {
            $pdo->prepare('UPDATE order_delivery_tracking SET rider_id = NULL, latitude = NULL, longitude = NULL, location_updated_at = NULL, delivery_started_at = NULL WHERE reference = ?')
                ->execute([$reference]);
        } elseif ((int) $currentRider !== $riderId) {
            $pdo->prepare('UPDATE order_delivery_tracking SET rider_id = ?, latitude = NULL, longitude = NULL, location_updated_at = NULL, delivery_started_at = NULL WHERE reference = ?')
                ->execute([$riderId, $reference]);
        }
        if ($riderId !== null && $status === 'out_for_delivery') {
            $pdo->prepare('UPDATE order_delivery_tracking SET delivery_started_at = COALESCE(delivery_started_at, NOW()) WHERE reference = ?')->execute([$reference]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    tradehub_admin_redirect('tradehub-orders', 'Order ' . $reference . ' updated.');
}

function tradehub_admin_user_action(PDO $pdo, string $action): void
{
    $id = absint($_POST['id'] ?? 0);
    if ($id < 1) {
        tradehub_admin_redirect('tradehub-users', 'Select a valid user.', 'error');
    }

    $stmt = $pdo->prepare('SELECT u.role_id, u.status, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        tradehub_admin_redirect('tradehub-users', 'That user no longer exists.', 'error');
    }

    if ($action === 'change_role') {
        $roleId = absint($_POST['role_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        $newRole = $stmt->fetchColumn();
        if (!$newRole) {
            tradehub_admin_redirect('tradehub-users', 'Choose a valid role.', 'error');
        }
        if ($user['role_name'] === 'Administrator' && $newRole !== 'Administrator') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "Administrator" AND u.status = "active"');
            $stmt->execute();
            if ((int) $stmt->fetchColumn() <= 1) {
                tradehub_admin_redirect('tradehub-users', 'At least one active TradeHub administrator must remain.', 'error');
            }
        }
        $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $id]);
        tradehub_admin_redirect('tradehub-users', 'User role updated.');
    }

    if ($action === 'toggle_user') {
        if ($user['status'] === 'active' && $user['role_name'] === 'Administrator') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "Administrator" AND u.status = "active"');
            $stmt->execute();
            if ((int) $stmt->fetchColumn() <= 1) {
                tradehub_admin_redirect('tradehub-users', 'At least one active TradeHub administrator must remain.', 'error');
            }
        }
        $pdo->prepare('UPDATE users SET status = IF(status = "active", "inactive", "active") WHERE id = ?')->execute([$id]);
        tradehub_admin_redirect('tradehub-users', 'User status updated.');
    }
}

function tradehub_admin_page_start(string $title, string $description = ''): void
{
    echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
    if ($description !== '') {
        echo '<p>' . esc_html($description) . '</p>';
    }
}

function tradehub_admin_page_end(): void
{
    echo '</div>';
}

function tradehub_admin_form_start(string $page, string $action, bool $multipart = false): void
{
    printf('<form method="post" action="%s"%s>', esc_url(tradehub_admin_url($page)), $multipart ? ' enctype="multipart/form-data"' : '');
    wp_nonce_field('tradehub_' . $action);
    printf('<input type="hidden" name="tradehub_page" value="%s"><input type="hidden" name="tradehub_action" value="%s">', esc_attr($page), esc_attr($action));
}

function tradehub_admin_money($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function tradehub_admin_render_dashboard(): void
{
    try {
        $pdo = tradehub_admin_db();
        $totals = $pdo->query("SELECT COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_amount END), 0) AS revenue, COUNT(DISTINCT reference) AS orders, SUM(status = 'pending') AS pending_lines FROM transactions")->fetch();
        $counts = $pdo->query("SELECT (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products, (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'Customer') AS customers")->fetch();
        $rate = (float) tradehub_admin_setting('commission_rate', '0');
        $recent = $pdo->query('SELECT t.reference, t.product_name, t.quantity, t.total_amount, t.status, t.created_at, u.full_name FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC, t.id DESC LIMIT 8')->fetchAll();
        $lowStock = $pdo->query('SELECT p.name, p.stock, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.stock <= 10 ORDER BY p.stock, p.name LIMIT 6')->fetchAll();
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }

    tradehub_admin_page_start('TradeHub dashboard', 'Marketplace activity at a glance.');
    echo '<div class="th-admin-stats">';
    tradehub_admin_stat('Total sales', tradehub_admin_money($totals['revenue']), (int) $totals['orders'] . ' orders');
    tradehub_admin_stat('Commission', tradehub_admin_money((float) $totals['revenue'] * $rate / 100), number_format($rate, 2) . '% platform rate');
    tradehub_admin_stat('Active products', (int) $counts['products'], (int) $totals['pending_lines'] . ' pending line items');
    tradehub_admin_stat('Customers', (int) $counts['customers'], 'Registered buyers');
    echo '</div><div class="th-admin-columns"><section><h2>Recent transactions</h2>';
    if (!$recent) {
        echo '<p>No transactions yet.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Reference</th><th>Customer</th><th>Item</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
        foreach ($recent as $row) {
            printf('<tr><td>%s<br><small>%s</small></td><td>%s</td><td>%s × %d</td><td>%s</td><td>%s</td></tr>',
                esc_html($row['reference']), esc_html($row['created_at']), esc_html($row['full_name']),
                esc_html($row['product_name']), (int) $row['quantity'], esc_html(tradehub_admin_money($row['total_amount'])),
                esc_html(ucwords(str_replace('_', ' ', $row['status']))));
        }
        echo '</tbody></table>';
    }
    echo '</section><section><h2>Low stock</h2>';
    if (!$lowStock) {
        echo '<p>All active products have healthy stock levels.</p>';
    } else {
        echo '<ul>';
        foreach ($lowStock as $item) {
            printf('<li><strong>%s</strong> — %s, %d left</li>', esc_html($item['name']), esc_html($item['category_name']), (int) $item['stock']);
        }
        echo '</ul>';
    }
    echo '</section></div>';
    tradehub_admin_page_end();
}

function tradehub_admin_stat(string $label, $value, string $detail): void
{
    printf('<div class="th-admin-stat"><span>%s</span><strong>%s</strong><small>%s</small></div>', esc_html($label), esc_html((string) $value), esc_html($detail));
}

function tradehub_admin_render_products(): void
{
    try {
        $pdo = tradehub_admin_db();
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.is_active DESC, p.name')->fetchAll();
        $editing = null;
        if (isset($_GET['edit'])) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([absint($_GET['edit'])]);
            $editing = $stmt->fetch() ?: null;
        }
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }

    tradehub_admin_page_start('Products', count($products) . ' listings in the catalog.');
    echo '<p><a class="button button-primary" href="' . esc_url(tradehub_admin_url('tradehub-products', ['new' => 1])) . '">Add product</a></p>';
    if (isset($_GET['new']) || $editing) {
        $row = $editing ?: ['id' => 0, 'name' => '', 'category_id' => 0, 'description' => '', 'image' => '', 'price' => '', 'stock' => 0, 'is_active' => 1];
        echo '<h2>' . ($editing ? 'Edit product' : 'New product') . '</h2>';
        tradehub_admin_form_start('tradehub-products', 'save_product', true);
        echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><table class="form-table"><tbody>';
        tradehub_admin_input('Product name', 'name', $row['name']);
        echo '<tr><th><label for="th-category">Category</label></th><td><select id="th-category" name="category_id" required>';
        foreach ($categories as $category) {
            printf('<option value="%d" %s>%s</option>', (int) $category['id'], selected((int) $row['category_id'], (int) $category['id'], false), esc_html($category['name']));
        }
        echo '</select></td></tr>';
        tradehub_admin_input('Price (₱)', 'price', $row['price'], 'number', '0.01', '0');
        tradehub_admin_input('Stock', 'stock', $row['stock'], 'number', '1', '0');
        echo '<tr><th><label for="th-image">Product photo</label></th><td><input id="th-image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><p class="description">WordPress Media Library upload. JPG, PNG, WebP or GIF.</p>';
        if (!empty($row['image'])) {
            if (preg_match('~^https?://~i', (string) $row['image'])) {
                printf('<p><img src="%s" alt="" style="max-width:120px;height:auto"></p>', esc_url($row['image']));
            } else {
                echo '<p class="description">Current TradeHub image: ' . esc_html($row['image']) . '</p>';
            }
            echo '<label><input type="checkbox" name="remove_image" value="1"> Remove current photo</label>';
        }
        echo '</td></tr><tr><th><label for="th-description">Description</label></th><td><textarea class="large-text" rows="4" id="th-description" name="description">' . esc_textarea($row['description']) . '</textarea></td></tr>';
        echo '<tr><th>Visibility</th><td><label><input type="checkbox" name="is_active" value="1" ' . checked((int) $row['is_active'], 1, false) . '> Show in storefront</label></td></tr></tbody></table>';
        submit_button($editing ? 'Save product' : 'Create product');
        echo '</form>';
    }
    echo '<table class="widefat striped"><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($products as $product) {
        echo '<tr><td><strong>' . esc_html($product['name']) . '</strong></td><td>' . esc_html($product['category_name']) . '</td><td>' . esc_html(tradehub_admin_money($product['price'])) . '</td><td>' . (int) $product['stock'] . '</td><td>' . ((int) $product['is_active'] ? 'Live' : 'Hidden') . '</td><td>';
        echo '<a class="button button-small" href="' . esc_url(tradehub_admin_url('tradehub-products', ['edit' => (int) $product['id']])) . '">Edit</a> ';
        tradehub_admin_row_action('tradehub-products', 'toggle_product', (int) $product['id'], (int) $product['is_active'] ? 'Hide' : 'Show');
        tradehub_admin_row_action('tradehub-products', 'delete_product', (int) $product['id'], 'Delete');
        echo '</td></tr>';
    }
    if (!$products) {
        echo '<tr><td colspan="6">No products yet.</td></tr>';
    }
    echo '</tbody></table>';
    tradehub_admin_page_end();
}

function tradehub_admin_render_categories(): void
{
    try {
        $pdo = tradehub_admin_db();
        $categories = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
        $editing = null;
        if (isset($_GET['edit'])) {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([absint($_GET['edit'])]);
            $editing = $stmt->fetch() ?: null;
        }
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }

    tradehub_admin_page_start('Categories', 'Group listings so buyers can filter the catalog.');
    echo '<p><a class="button button-primary" href="' . esc_url(tradehub_admin_url('tradehub-categories', ['new' => 1])) . '">Add category</a></p>';
    if (isset($_GET['new']) || $editing) {
        $row = $editing ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => ''];
        echo '<h2>' . ($editing ? 'Edit category' : 'New category') . '</h2>';
        tradehub_admin_form_start('tradehub-categories', 'save_category');
        echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><table class="form-table"><tbody>';
        tradehub_admin_input('Category name', 'name', $row['name']);
        tradehub_admin_input('URL slug (optional)', 'slug', $row['slug']);
        echo '<tr><th><label for="th-cat-description">Description</label></th><td><textarea class="large-text" id="th-cat-description" name="description">' . esc_textarea($row['description']) . '</textarea></td></tr></tbody></table>';
        submit_button($editing ? 'Save category' : 'Create category');
        echo '</form>';
    }
    echo '<table class="widefat striped"><thead><tr><th>Category</th><th>Slug</th><th>Products</th><th>Actions</th></tr></thead><tbody>';
    foreach ($categories as $category) {
        echo '<tr><td><strong>' . esc_html($category['name']) . '</strong><br>' . esc_html((string) $category['description']) . '</td><td><code>' . esc_html($category['slug']) . '</code></td><td>' . (int) $category['product_count'] . '</td><td><a class="button button-small" href="' . esc_url(tradehub_admin_url('tradehub-categories', ['edit' => (int) $category['id']])) . '">Edit</a> ';
        tradehub_admin_row_action('tradehub-categories', 'delete_category', (int) $category['id'], 'Delete', (int) $category['product_count'] > 0);
        echo '</td></tr>';
    }
    if (!$categories) {
        echo '<tr><td colspan="4">No categories yet.</td></tr>';
    }
    echo '</tbody></table>';
    tradehub_admin_page_end();
}

function tradehub_admin_render_orders(): void
{
    try {
        $pdo = tradehub_admin_db();
        $rows = $pdo->query('SELECT t.*, u.full_name, u.email, u.address AS profile_address, d.delivery_address, d.rider_id, d.location_updated_at FROM transactions t JOIN users u ON u.id = t.user_id LEFT JOIN order_delivery_tracking d ON d.reference = t.reference ORDER BY t.created_at DESC, t.id DESC')->fetchAll();
        $riders = $pdo->query('SELECT u.id, u.full_name, u.email FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "Delivery Rider" AND u.status = "active" ORDER BY u.full_name')->fetchAll();
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }
    $orders = [];
    foreach ($rows as $row) {
        $ref = $row['reference'];
        if (!isset($orders[$ref])) {
            $orders[$ref] = [
                'reference' => $ref, 'customer' => $row['full_name'], 'email' => $row['email'],
                'created_at' => $row['created_at'], 'status' => $row['status'],
                'payment_method' => $row['payment_method'],
                'address' => $row['delivery_address'] ?: $row['profile_address'],
                'tracking_number' => $row['tracking_number'], 'rider_id' => $row['rider_id'],
                'location_updated_at' => $row['location_updated_at'], 'total' => 0, 'items' => [],
            ];
        }
        $orders[$ref]['items'][] = $row;
        $orders[$ref]['total'] += (float) $row['total_amount'];
        $rank = ['cancelled' => 0, 'pending' => 1, 'paid' => 2, 'out_for_delivery' => 3, 'completed' => 4];
        if ($rank[$row['status']] < $rank[$orders[$ref]['status']]) {
            $orders[$ref]['status'] = $row['status'];
        }
    }

    tradehub_admin_page_start('Orders', count($orders) . ' orders. Update status, assign COD riders, and add tracking numbers.');
    $statuses = ['pending', 'paid', 'out_for_delivery', 'completed', 'cancelled'];
    foreach ($orders as $order) {
        echo '<section class="th-order"><h2>' . esc_html($order['reference']) . ' — ' . esc_html(tradehub_admin_money($order['total'])) . '</h2>';
        printf('<p>%s · %s (%s)<br>Payment: %s · Deliver to: %s</p>', esc_html($order['created_at']), esc_html($order['customer']), esc_html($order['email']), esc_html(strtoupper($order['payment_method'])), esc_html((string) ($order['address'] ?: 'No address provided')));
        echo '<ul>';
        foreach ($order['items'] as $item) {
            printf('<li>%s × %d — %s</li>', esc_html($item['product_name']), (int) $item['quantity'], esc_html(tradehub_admin_money($item['total_amount'])));
        }
        echo '</ul>';
        tradehub_admin_form_start('tradehub-orders', 'update_order');
        echo '<input type="hidden" name="reference" value="' . esc_attr($order['reference']) . '"><div class="th-admin-inline"><label>Status <select name="status">';
        foreach ($statuses as $status) {
            printf('<option value="%s" %s>%s</option>', esc_attr($status), selected($order['status'], $status, false), esc_html(ucwords(str_replace('_', ' ', $status))));
        }
        echo '</select></label><label>Tracking number <input type="text" name="tracking_number" maxlength="64" value="' . esc_attr((string) $order['tracking_number']) . '"></label><label>Delivery rider <select name="rider_id" ' . ($order['payment_method'] !== 'cod' ? 'disabled' : '') . '><option value="">Unassigned</option>';
        foreach ($riders as $rider) {
            printf('<option value="%d" %s>%s (%s)</option>', (int) $rider['id'], selected((int) $order['rider_id'], (int) $rider['id'], false), esc_html($rider['full_name']), esc_html($rider['email']));
        }
        echo '</select></label>';
        submit_button('Save order', 'primary', 'submit', false);
        echo '</div></form>';
        if ($order['location_updated_at']) {
            echo '<p class="description">Last rider GPS update: ' . esc_html($order['location_updated_at']) . '</p>';
        }
        echo '</section>';
    }
    if (!$orders) {
        echo '<p>No orders yet.</p>';
    }
    tradehub_admin_page_end();
}

function tradehub_admin_render_users(): void
{
    try {
        $pdo = tradehub_admin_db();
        $roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
        $users = $pdo->query('SELECT u.*, r.name AS role_name, (SELECT COUNT(DISTINCT t.reference) FROM transactions t WHERE t.user_id = u.id) AS order_count, (SELECT COALESCE(SUM(t.total_amount), 0) FROM transactions t WHERE t.user_id = u.id AND t.status <> "cancelled") AS spent FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC')->fetchAll();
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }
    tradehub_admin_page_start('Users', count($users) . ' TradeHub accounts.');
    echo '<table class="widefat striped"><thead><tr><th>User</th><th>Contact</th><th>Role</th><th>Orders</th><th>Spent</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($users as $user) {
        echo '<tr><td><strong>' . esc_html($user['full_name']) . '</strong><br>' . esc_html($user['email']) . '</td><td>' . esc_html((string) $user['phone']) . '<br>' . esc_html((string) $user['address']) . '</td><td>';
        tradehub_admin_form_start('tradehub-users', 'change_role');
        echo '<input type="hidden" name="id" value="' . (int) $user['id'] . '"><select name="role_id" onchange="this.form.submit()">';
        foreach ($roles as $role) {
            printf('<option value="%d" %s>%s</option>', (int) $role['id'], selected((int) $user['role_id'], (int) $role['id'], false), esc_html($role['name']));
        }
        echo '</select><noscript><button class="button button-small">Save role</button></noscript></form></td>';
        printf('<td>%d</td><td>%s</td><td>%s</td><td>', (int) $user['order_count'], esc_html(tradehub_admin_money($user['spent'])), esc_html(ucfirst($user['status'])));
        tradehub_admin_row_action('tradehub-users', 'toggle_user', (int) $user['id'], $user['status'] === 'active' ? 'Deactivate' : 'Activate');
        echo '</td></tr>';
    }
    if (!$users) {
        echo '<tr><td colspan="7">No users yet.</td></tr>';
    }
    echo '</tbody></table>';
    tradehub_admin_page_end();
}

function tradehub_admin_render_reports(): void
{
    $from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : date('Y-m-01');
    $to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : date('Y-m-d');
    $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
    try {
        $pdo = tradehub_admin_db();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS gross, COUNT(DISTINCT reference) AS orders, COALESCE(SUM(quantity), 0) AS units FROM transactions WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ?");
        $stmt->execute($params);
        $summary = $stmt->fetch();
        $rate = (float) tradehub_admin_setting('commission_rate', '0');
        $stmt = $pdo->prepare('SELECT c.name, SUM(t.total_amount) AS revenue, SUM(t.quantity) AS units FROM transactions t JOIN products p ON p.id = t.product_id JOIN categories c ON c.id = p.category_id WHERE t.status <> "cancelled" AND t.created_at BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY revenue DESC');
        $stmt->execute($params);
        $categories = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT t.product_name, SUM(t.quantity) AS units, SUM(t.total_amount) AS revenue FROM transactions t WHERE t.status <> "cancelled" AND t.created_at BETWEEN ? AND ? GROUP BY t.product_name ORDER BY revenue DESC LIMIT 5');
        $stmt->execute($params);
        $top = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT t.reference, t.product_name, t.quantity, t.total_amount, t.payment_method, t.status, t.created_at, u.full_name FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.created_at BETWEEN ? AND ? ORDER BY t.created_at DESC, t.id DESC LIMIT 50');
        $stmt->execute($params);
        $log = $stmt->fetchAll();
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }
    $gross = (float) $summary['gross'];
    $commission = $gross * $rate / 100;
    $avg = (int) $summary['orders'] > 0 ? $gross / (int) $summary['orders'] : 0;
    tradehub_admin_page_start('Sales reports', 'Sales, commission, and best sellers for a date range.');
    echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="tradehub-reports"><label>From <input type="date" name="from" value="' . esc_attr($from) . '"></label> <label>To <input type="date" name="to" value="' . esc_attr($to) . '"></label> ';
    submit_button('Generate', 'primary', '', false);
    echo '</form><div class="th-admin-stats">';
    tradehub_admin_stat('Gross sales', tradehub_admin_money($gross), (int) $summary['orders'] . ' orders · ' . (int) $summary['units'] . ' units');
    tradehub_admin_stat('Commission', tradehub_admin_money($commission), number_format($rate, 2) . '%');
    tradehub_admin_stat('Seller payout', tradehub_admin_money($gross - $commission), 'After commission');
    tradehub_admin_stat('Average order', tradehub_admin_money($avg), $from . ' to ' . $to);
    echo '</div><div class="th-admin-columns"><section><h2>Sales by category</h2><table class="widefat striped"><thead><tr><th>Category</th><th>Units</th><th>Revenue</th></tr></thead><tbody>';
    foreach ($categories as $row) {
        printf('<tr><td>%s</td><td>%d</td><td>%s</td></tr>', esc_html($row['name']), (int) $row['units'], esc_html(tradehub_admin_money($row['revenue'])));
    }
    if (!$categories) {
        echo '<tr><td colspan="3">No sales recorded in this period.</td></tr>';
    }
    echo '</tbody></table></section><section><h2>Top products</h2><table class="widefat striped"><thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead><tbody>';
    foreach ($top as $row) {
        printf('<tr><td>%s</td><td>%d</td><td>%s</td></tr>', esc_html($row['product_name']), (int) $row['units'], esc_html(tradehub_admin_money($row['revenue'])));
    }
    if (!$top) {
        echo '<tr><td colspan="3">No products sold in this period.</td></tr>';
    }
    echo '</tbody></table></section></div><h2>Transaction log (latest 50)</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Item</th><th>Qty</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead><tbody>';
    foreach ($log as $row) {
        printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($row['created_at']), esc_html($row['reference']), esc_html($row['full_name']), esc_html($row['product_name']), (int) $row['quantity'], esc_html(tradehub_admin_money($row['total_amount'])), esc_html(strtoupper($row['payment_method'])), esc_html(ucwords(str_replace('_', ' ', $row['status']))));
    }
    if (!$log) {
        echo '<tr><td colspan="8">No transactions in this date range.</td></tr>';
    }
    echo '</tbody></table>';
    tradehub_admin_page_end();
}

function tradehub_admin_render_content(): void
{
    try {
        $rate = tradehub_admin_setting('commission_rate', '0');
        tradehub_admin_db();
    } catch (Throwable $error) {
        tradehub_admin_render_db_error($error);
        return;
    }
    tradehub_admin_page_start('Storefront content', 'Manage the content read by the TradeHub storefront and REST API.');
    tradehub_admin_form_start('tradehub-content', 'save_content');
    echo '<table class="form-table"><tbody>';
    tradehub_admin_input('Site name', 'site_name', get_option('tradehub_site_name', 'TradeHub'));
    tradehub_admin_input('Tagline', 'tagline', get_option('tradehub_tagline', ''));
    tradehub_admin_input('Home headline', 'hero_title', get_option('tradehub_hero_title', ''));
    tradehub_admin_textarea('Home introduction', 'hero_text', get_option('tradehub_hero_text', ''));
    tradehub_admin_textarea('Business overview', 'about_text', get_option('tradehub_about_text', ''));
    tradehub_admin_input('Contact email', 'contact_email', get_option('tradehub_contact_email', ''), 'email');
    tradehub_admin_input('Contact phone', 'contact_phone', get_option('tradehub_contact_phone', ''));
    tradehub_admin_input('Commission rate (%)', 'commission_rate', $rate, 'number', '0.01', '0', '100');
    echo '</tbody></table>';
    submit_button('Save storefront content');
    echo '</form><hr><h2>Announcements</h2><p>Create and publish storefront announcements using the WordPress editor.</p><p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=th_announcement')) . '">Add announcement</a> <a class="button" href="' . esc_url(admin_url('edit.php?post_type=th_announcement')) . '">Manage announcements</a></p>';
    tradehub_admin_page_end();
}

function tradehub_admin_setting(string $key, string $default = ''): string
{
    $stmt = tradehub_admin_db()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function tradehub_admin_input(string $label, string $name, $value, string $type = 'text', string $step = '', string $min = '', string $max = ''): void
{
    $attrs = $type === 'number' ? ' step="' . esc_attr($step) . '" min="' . esc_attr($min) . '"' . ($max !== '' ? ' max="' . esc_attr($max) . '"' : '') : '';
    printf('<tr><th><label for="th-%s">%s</label></th><td><input class="regular-text" id="th-%s" name="%s" type="%s" value="%s"%s></td></tr>', esc_attr($name), esc_html($label), esc_attr($name), esc_attr($name), esc_attr($type), esc_attr((string) $value), $attrs);
}

function tradehub_admin_textarea(string $label, string $name, string $value): void
{
    printf('<tr><th><label for="th-%s">%s</label></th><td><textarea class="large-text" rows="4" id="th-%s" name="%s">%s</textarea></td></tr>', esc_attr($name), esc_html($label), esc_attr($name), esc_attr($name), esc_textarea($value));
}

function tradehub_admin_row_action(string $page, string $action, int $id, string $label, bool $disabled = false): void
{
    tradehub_admin_form_start($page, $action);
    printf('<input type="hidden" name="id" value="%d">', $id);
    submit_button($label, 'secondary small', 'submit', false, $disabled ? ['disabled' => 'disabled'] : []);
    echo '</form> ';
}

function tradehub_admin_render_db_error(Throwable $error): void
{
    error_log('TradeHub WordPress admin database connection failed: ' . $error->getMessage());
    echo '<div class="notice notice-error"><p><strong>TradeHub database is not available.</strong> Check the database constants in wp-config.php and verify that the WordPress database user can access the TradeHub database. See the setup instructions in README.md.</p></div>';
}

function tradehub_admin_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'tradehub') === false) {
        return;
    }
    echo '<style>
      .th-admin-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0}
      .th-admin-stat,.th-order{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:18px;margin:16px 0}
      .th-admin-stat span,.th-admin-stat small{display:block;color:#646970}
      .th-admin-stat strong{display:block;font-size:24px;margin:8px 0}
      .th-admin-columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:22px;margin-top:24px}
      .th-admin-inline{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
      .th-admin-inline label{display:flex;flex-direction:column;gap:4px}
      .th-admin-inline form{display:inline}
      @media(max-width:782px){.th-admin-inline{align-items:stretch}.th-admin-inline label{width:100%}}
    </style>';
}
add_action('admin_head', 'tradehub_admin_styles');

