<?php
require __DIR__ . '/includes/bootstrap.php';
require_delivery_rider();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

require_csrf();

$reference = post('reference');
$latitude = post('latitude');
$longitude = post('longitude');

if (
    $reference === ''
    || mb_strlen($reference) > 20
    || !is_numeric($latitude)
    || !is_numeric($longitude)
    || !is_finite((float) $latitude)
    || !is_finite((float) $longitude)
    || (float) $latitude < -90
    || (float) $latitude > 90
    || (float) $longitude < -180
    || (float) $longitude > 180
) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid order and GPS coordinate are required.']);
    exit;
}

$rider = current_user();
$assigned = db()->prepare(
    'SELECT 1
     FROM transactions t
     JOIN order_delivery_tracking d ON d.reference = t.reference
     WHERE t.reference = ? AND d.rider_id = ?
       AND t.payment_method = "cod" AND t.status = "out_for_delivery"
     LIMIT 1'
);
$assigned->execute([$reference, $rider['id']]);

if (!$assigned->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'This active COD delivery is not assigned to your account.']);
    exit;
}

db()->prepare(
    'UPDATE order_delivery_tracking
     SET latitude = ?, longitude = ?, location_updated_at = NOW()
     WHERE reference = ? AND rider_id = ?'
)->execute([(float) $latitude, (float) $longitude, $reference, $rider['id']]);

echo json_encode(['ok' => true, 'updated_at' => date(DATE_ATOM)]);
