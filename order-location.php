<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$reference = $_GET['ref'] ?? '';
if (!is_string($reference) || $reference === '' || mb_strlen($reference) > 20) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid order reference is required.']);
    exit;
}

$stmt = db()->prepare(
    'SELECT t.status, t.payment_method, d.rider_id, d.latitude, d.longitude,
            d.location_updated_at
     FROM transactions t
     LEFT JOIN order_delivery_tracking d ON d.reference = t.reference
     WHERE t.reference = ? AND t.user_id = ?
     ORDER BY t.id
     LIMIT 1'
);
$stmt->execute([$reference, current_user()['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found.']);
    exit;
}

$updatedAt = $order['location_updated_at'] === null
    ? null
    : date(DATE_ATOM, strtotime($order['location_updated_at']));

echo json_encode([
    'status' => $order['status'],
    'rider_assigned' => $order['rider_id'] !== null,
    'latitude' => $order['status'] === 'cancelled' || $order['latitude'] === null ? null : (float) $order['latitude'],
    'longitude' => $order['status'] === 'cancelled' || $order['longitude'] === null ? null : (float) $order['longitude'],
    'location_updated_at' => $updatedAt,
]);
