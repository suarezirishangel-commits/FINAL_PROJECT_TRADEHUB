<?php
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function paypal_capture_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!current_user()) {
    paypal_capture_response(401, ['message' => 'Please log in again before completing your PayPal payment.']);
}
require_csrf();

$orderId = post('order_id');
if ($orderId === '' || strlen($orderId) > 64 || !preg_match('/^[A-Z0-9-]+$/', $orderId)) {
    paypal_capture_response(422, ['message' => 'The PayPal order ID is invalid.']);
}

$lookup = db()->prepare('SELECT * FROM paypal_payments WHERE paypal_order_id = ? AND user_id = ?');
$lookup->execute([$orderId, current_user()['id']]);
$payment = $lookup->fetch();
if (!$payment) {
    paypal_capture_response(404, ['message' => 'PayPal order not found.']);
}
if ($payment['status'] === 'completed') {
    paypal_capture_response(200, ['redirect_url' => url('orders.php?ref=' . urlencode((string) $payment['reference']))]);
}
if (!in_array($payment['status'], ['created', 'captured'], true)) {
    paypal_capture_response(409, ['message' => 'This PayPal checkout is no longer available.']);
}

try {
    $checkout = json_decode($payment['checkout_data'], true, 512, JSON_THROW_ON_ERROR);
    $capture = paypal_capture_order(
        $payment['paypal_order_id'],
        number_format((float) $payment['amount'], 2, '.', ''),
        (int) $payment['user_id'],
        (string) $checkout['reference']
    );
} catch (RuntimeException $exception) {
    paypal_capture_response(502, ['message' => $exception->getMessage()]);
}
db()->prepare(
    'UPDATE paypal_payments SET status = "captured", capture_id = ?, updated_at = NOW()
     WHERE paypal_order_id = ? AND status = "created"'
)->execute([$capture['capture_id'], $payment['paypal_order_id']]);

$reference = (string) $checkout['reference'];
$inventoryError = null;
$alreadyCompleted = false;
$pdo = db();
$pdo->beginTransaction();
try {
    $lockedStmt = $pdo->prepare('SELECT status, reference FROM paypal_payments WHERE paypal_order_id = ? FOR UPDATE');
    $lockedStmt->execute([$payment['paypal_order_id']]);
    $lockedPayment = $lockedStmt->fetch();
    if ($lockedPayment['status'] === 'completed') {
        $alreadyCompleted = true;
        $reference = (string) $lockedPayment['reference'];
        $pdo->commit();
    } else {
        $productIds = array_values(array_unique(array_map(
            static fn (array $item): int => (int) $item['product_id'],
            $checkout['items']
        )));
        sort($productIds);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $productStmt = $pdo->prepare(
            "SELECT id, stock FROM products WHERE id IN ($placeholders) AND is_active = 1 FOR UPDATE"
        );
        $productStmt->execute($productIds);
        $products = [];
        foreach ($productStmt->fetchAll() as $product) {
            $products[(int) $product['id']] = $product;
        }

        foreach ($checkout['items'] as $item) {
            $id = (int) $item['product_id'];
            if (!isset($products[$id]) || (int) $products[$id]['stock'] < (int) $item['quantity']) {
                $inventoryError = isset($products[$id])
                    ? $item['product_name'] . ' no longer has enough stock.'
                    : $item['product_name'] . ' is no longer available.';
                break;
            }
        }

        if ($inventoryError !== null) {
            $pdo->rollBack();
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO transactions
                 (reference, user_id, product_id, seller_id, product_name, quantity, unit_price, total_amount, payment_method, status, paid_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "paypal", "paid", NOW())'
            );
            $decrement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($checkout['items'] as $item) {
                $insert->execute([
                    $reference,
                    $payment['user_id'],
                    $item['product_id'],
                    $item['seller_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_amount'],
                ]);
                $decrement->execute([$item['quantity'], $item['product_id']]);
            }

            $delivery = $pdo->prepare('INSERT INTO order_delivery_tracking (reference, delivery_address) VALUES (?, ?)');
            $delivery->execute([$reference, $checkout['address']]);
            $complete = $pdo->prepare(
                'UPDATE paypal_payments
                 SET reference = ?, status = "completed", capture_id = ?, updated_at = NOW()
                 WHERE paypal_order_id = ?'
            );
            $complete->execute([$reference, $capture['capture_id'], $payment['paypal_order_id']]);
            $pdo->commit();
        }
    }
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('TradeHub could not finalize PayPal order ' . $payment['paypal_order_id'] . ': ' . $exception->getMessage());
    paypal_capture_response(500, ['message' => 'PayPal captured your payment, but TradeHub could not finish the order. Contact support with PayPal order ID ' . $payment['paypal_order_id'] . '.']);
}

if ($inventoryError !== null) {
    try {
        $refundStatus = paypal_refund_capture($capture['capture_id']);
        $paymentStatus = $refundStatus === 'COMPLETED' ? 'refunded' : 'refund_pending';
        $pdo->prepare('UPDATE paypal_payments SET status = ?, updated_at = NOW() WHERE paypal_order_id = ?')
            ->execute([$paymentStatus, $payment['paypal_order_id']]);
        $message = $refundStatus === 'COMPLETED'
            ? 'The item became unavailable after payment. PayPal has refunded your payment.'
            : 'The item became unavailable after payment. Your PayPal refund is being processed.';
    } catch (RuntimeException $exception) {
        error_log('TradeHub PayPal refund failed for order ' . $payment['paypal_order_id'] . ': ' . $exception->getMessage());
        $pdo->prepare('UPDATE paypal_payments SET status = "refund_pending", updated_at = NOW() WHERE paypal_order_id = ?')
            ->execute([$payment['paypal_order_id']]);
        $message = 'The item became unavailable after payment. Contact TradeHub support with PayPal order ID ' . $payment['paypal_order_id'] . ' to complete your refund.';
    }
    paypal_capture_response(409, ['message' => $message]);
}

if (!$alreadyCompleted) {
    $user = current_user();
    $user['full_name'] = $checkout['full_name'];
    $user['phone'] = $checkout['phone'];
    $user['address'] = $checkout['address'];
    $total = array_sum(array_map(static fn (array $item): float => (float) $item['total_amount'], $checkout['items']));
    $mailed = send_order_confirmation($user, $reference, $checkout['items'], $total, 'paypal');
    $_SESSION['cart'] = [];
}

paypal_capture_response(200, ['redirect_url' => url('orders.php?ref=' . urlencode($reference))]);
