<?php
declare(strict_types=1);

/** App configuration array. */
function config(): array
{
    static $cfg = null;
    return $cfg ??= require __DIR__ . '/../config/config.php';
}

/** Escape output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute-path URL for a file inside the app. */
function url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Store a one-time flash message. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear flash messages. */
function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Ensure session CSRF token exists. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Reject non-POST or invalid-token submissions. */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, reload the page, and try again.');
    }
}

/** Trimmed POST value. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Logged-in user with role name, or null. */
function current_user(): ?array
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $user = null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.full_name, u.email, u.phone, u.address, u.status, u.role_id, r.name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.status = "active"'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role_name'] === 'Administrator';
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('403 — Administrator access required.');
    }
}

/** Site content / settings value. */
function setting(string $key, string $default = ''): string
{
    static $settings = null;

    $wordpressContent = wordpress_cms_content();
    if ($wordpressContent !== null && isset($wordpressContent['settings'][$key]) && is_string($wordpressContent['settings'][$key])) {
        return $wordpressContent['settings'][$key];
    }

    if ($settings === null) {
        $settings = [];
        foreach (db()->query('SELECT `key`, `value` FROM settings') as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }

    return $settings[$key] ?? $default;
}

/** Fetch public storefront content from the optional WordPress CMS. */
function wordpress_cms_content(): ?array
{
    static $resolved = false;
    static $content = null;

    if ($resolved) {
        return $content;
    }
    $resolved = true;

    $wordpressUrl = trim((string) config()['cms']['wordpress_url']);
    if ($wordpressUrl === '') {
        return null;
    }

    $endpoint = $wordpressUrl . '/wp-json/tradehub/v1/content';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $body = file_get_contents($endpoint, false, $context);
        if ($body === false) {
            throw new UnexpectedValueException('The WordPress CMS returned an empty response.');
        }

        $status = 0;
        foreach (array_reverse($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }
        if ($status !== 200) {
            throw new UnexpectedValueException('The WordPress CMS returned HTTP ' . $status . '.');
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['settings'], $decoded['announcements'])
            || !is_array($decoded['settings']) || !is_array($decoded['announcements'])) {
            throw new UnexpectedValueException('The WordPress CMS response has an invalid format.');
        }

        return $content = $decoded;
    } catch (ErrorException | JsonException | UnexpectedValueException $error) {
        error_log('TradeHub WordPress CMS request failed: ' . $error->getMessage());
        return null;
    } finally {
        restore_error_handler();
    }
}

/** Published announcements from WordPress, or the legacy local table if not configured. */
function storefront_announcements(): array
{
    $wordpressContent = wordpress_cms_content();
    if ($wordpressContent !== null) {
        return $wordpressContent['announcements'];
    }

    return db()->query(
        'SELECT title, body, created_at FROM announcements WHERE is_published = 1 ORDER BY created_at DESC LIMIT 10'
    )->fetchAll();
}

/** Whether the storefront is configured to use WordPress as its content source. */
function wordpress_cms_configured(): bool
{
    return trim((string) config()['cms']['wordpress_url']) !== '';
}

function format_price(float|string $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Simple color bucket per product so cards look distinct without images. */
function product_tone(int $id): int
{
    return $id % 5;
}

/** Public URL for a product photo, or null when the product has none. */
function product_image_url(?string $path): ?string
{
    $path = trim((string) $path);

    if ($path === '') {
        return null;
    }

    return preg_match('~^https?://~i', $path) ? $path : url($path);
}

/** Emoji illustration used to identify a product until a photo is uploaded. */
function product_icon(?string $categoryName): string
{
    $name = mb_strtolower(trim((string) $categoryName));

    $icons = [
        '🥬' => ['produce', 'vegetable', 'fruit', 'farm', 'meat', 'seafood', 'rice'],
        '🥖' => ['bake', 'bread', 'cake', 'pastry', 'dessert'],
        '🧺' => ['craft', 'artisan', 'handmade', 'woven', 'souvenir'],
        '☕' => ['beverage', 'drink', 'coffee', 'tea', 'juice'],
        '🥫' => ['pantry', 'sauce', 'condiment', 'preserve', 'spice'],
        '📱' => ['electronic', 'gadget', 'phone', 'computer', 'tech'],
        '👕' => ['cloth', 'apparel', 'fashion', 'wear', 'shoe'],
        '🧴' => ['beauty', 'care', 'cosmetic', 'soap'],
        '🧸' => ['toy', 'game', 'hobby'],
        '🪴' => ['plant', 'garden', 'flower', 'seed'],
        '🐾' => ['pet', 'animal'],
        '📚' => ['book', 'stationery', 'school'],
        '🪑' => ['furniture', 'home', 'decor'],
    ];

    foreach ($icons as $emoji => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return $emoji;
            }
        }
    }

    return '📦';
}

/**
 * Store an uploaded product photo.
 *
 * @return array{path: ?string, error: ?string}
 */
function store_product_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'The image could not be uploaded. Please try a smaller file.'];
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['path' => null, 'error' => 'The product photo must be 2MB or smaller.'];
    }

    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];

    if ($info === false || !isset($extensions[$info[2]])) {
        return ['path' => null, 'error' => 'The product photo must be a JPG, PNG, WebP, or GIF image.'];
    }

    $directory = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return ['path' => null, 'error' => 'The upload folder could not be created. Check folder permissions.'];
    }

    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$info[2]];

    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        return ['path' => null, 'error' => 'The uploaded image could not be saved.'];
    }

    return ['path' => 'assets/uploads/products/' . $filename, 'error' => null];
}

/** Delete a stored product photo (ignores external URLs and files outside the uploads folder). */
function delete_product_image(?string $path): void
{
    $path = trim((string) $path);
    if (!str_starts_with($path, 'assets/uploads/products/')) {
        return;
    }

    $file = __DIR__ . '/../' . $path;
    if (is_file($file)) {
        @unlink($file);
    }
}

/** Validate a Philippine-facing phone number, digits/spaces/+ only. */
function is_valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\s()-]{7,20}$/', $phone);
}

function order_reference(): string
{
    return 'TH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/** How far an order status has progressed; lower means less advanced. */
function order_status_rank(string $status): int
{
    return ['cancelled' => 0, 'pending' => 1, 'paid' => 2, 'completed' => 3][$status] ?? 0;
}

/**
 * Customer-facing tracking timeline for one order.
 *
 * @param array{status: string, created_at?: ?string, paid_at?: ?string, completed_at?: ?string, cancelled_at?: ?string} $order
 * @return array<int, array{label: string, at: ?string, done: bool, cancelled: bool}>
 */
function order_tracking_steps(array $order): array
{
    $status = (string) $order['status'];
    $paidAt = $order['paid_at'] ?? null;
    $completedAt = $order['completed_at'] ?? null;

    $steps = [
        ['label' => 'Order placed', 'at' => $order['created_at'] ?? null, 'done' => true, 'cancelled' => false],
        [
            'label'  => 'Payment confirmed',
            'at'     => $paidAt,
            'done'   => $paidAt !== null || in_array($status, ['paid', 'completed'], true),
            'cancelled' => false,
        ],
    ];

    if ($status === 'cancelled') {
        $steps[] = ['label' => 'Order cancelled', 'at' => $order['cancelled_at'] ?? null, 'done' => true, 'cancelled' => true];
    } else {
        $steps[] = [
            'label'  => 'Order completed',
            'at'     => $completedAt,
            'done'   => $completedAt !== null || $status === 'completed',
            'cancelled' => false,
        ];
    }

    return $steps;
}

/** Password rules: 8+ characters with at least one letter and one number. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}
