<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function paypal_checkout_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!current_user()) {
    paypal_checkout_response(401, ['message' => 'Please log in again before paying with PayPal.']);
}
require_csrf();

if (!paypal_is_configured()) {
    paypal_checkout_response(503, ['message' => 'PayPal is not configured. Add the PayPal credentials to the server environment.']);
}

$fullName = post('full_name');
$phone = post('phone');
$address = post('address');
if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120 || !is_valid_phone($phone) || mb_strlen($address) < 10 || mb_strlen($address) > 255) {
    paypal_checkout_response(422, ['message' => 'Enter a valid recipient name, phone number, and complete delivery address.']);
}

$cart = $_SESSION['cart'] ?? [];
if (!is_array($cart) || !$cart) {
    paypal_checkout_response(422, ['message' => 'Your cart is empty. Add a product before checking out.']);
}

$ids = array_values(array_unique(array_filter(array_map('intval', array_keys($cart)), static fn (int $id): bool => $id > 0)));
if (!$ids) {
    paypal_checkout_response(422, ['message' => 'The items in your cart are no longer available.']);
}
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$productStmt = db()->prepare(
    "SELECT id, seller_id, name, price, stock FROM products WHERE id IN ($placeholders) AND is_active = 1"
);
$productStmt->execute($ids);
$products = [];
foreach ($productStmt->fetchAll() as $product) {
    $products[(int) $product['id']] = $product;
}

$items = [];
$total = 0.0;
foreach ($cart as $id => $cartQuantity) {
    $id = (int) $id;
    $quantity = filter_var($cartQuantity, FILTER_VALIDATE_INT);
    if (!isset($products[$id]) || $quantity === false || $quantity < 1) {
        continue;
    }
    $product = $products[$id];
    if ((int) $product['stock'] < $quantity) {
        paypal_checkout_response(422, ['message' => $product['name'] . ' only has ' . (int) $product['stock'] . ' left in stock.']);
    }

    $lineTotal = round($quantity * (float) $product['price'], 2);
    $items[] = [
        'product_id' => $id,
        'seller_id' => $product['seller_id'] === null ? null : (int) $product['seller_id'],
        'product_name' => $product['name'],
        'quantity' => $quantity,
        'unit_price' => number_format((float) $product['price'], 2, '.', ''),
        'total_amount' => number_format($lineTotal, 2, '.', ''),
    ];
    $total += $lineTotal;
}

if (!$items) {
    paypal_checkout_response(422, ['message' => 'The items in your cart are no longer available.']);
}

$amount = number_format($total, 2, '.', '');
$reference = order_reference();
$user = current_user();
try {
    $paypalOrderId = paypal_create_order($amount, $reference, (int) $user['id']);
} catch (RuntimeException $exception) {
    paypal_checkout_response(502, ['message' => $exception->getMessage()]);
}

$checkoutData = json_encode([
    'reference' => $reference,
    'full_name' => $fullName,
    'phone' => $phone,
    'address' => $address,
    'items' => $items,
], JSON_THROW_ON_ERROR);
$insert = db()->prepare(
    'INSERT INTO paypal_payments
     (paypal_order_id, user_id, amount, currency, status, checkout_data)
     VALUES (?, ?, ?, ?, "created", ?)'
);
$insert->execute([$paypalOrderId, $user['id'], $amount, config()['paypal']['currency'], $checkoutData]);

paypal_checkout_response(200, ['id' => $paypalOrderId]);
