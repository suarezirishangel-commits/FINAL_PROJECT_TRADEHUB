<?php
declare(strict_types=1);

function paypal_is_configured(): bool
{
    $paypal = config()['paypal'];

    return in_array($paypal['mode'], ['sandbox', 'live'], true)
        && $paypal['client_id'] !== ''
        && $paypal['client_secret'] !== ''
        && preg_match('/^[A-Z]{3}$/', $paypal['currency']) === 1;
}

function paypal_api_request(string $method, string $path, ?array $payload = null, ?string $requestId = null): array
{
    if (!paypal_is_configured()) {
        throw new RuntimeException('PayPal is not configured. Add the PayPal credentials to the server environment.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PayPal checkout requires the PHP cURL extension.');
    }

    $paypal = config()['paypal'];
    $baseUrl = $paypal['mode'] === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';

    $curl = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => $paypal['client_id'] . ':' . $paypal['client_secret'],
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    ]);
    $tokenBody = curl_exec($curl);
    $tokenStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($tokenBody) || $tokenStatus < 200 || $tokenStatus >= 300) {
        error_log('TradeHub PayPal OAuth request failed. HTTP ' . $tokenStatus . ($curlError !== '' ? ': ' . $curlError : ''));
        throw new RuntimeException('PayPal could not authorize this payment. Please try again.');
    }
    $token = json_decode($tokenBody, true);
    if (!is_array($token) || !is_string($token['access_token'] ?? null)) {
        throw new RuntimeException('PayPal returned an invalid authorization response.');
    }

    $headers = ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token['access_token']];
    if ($requestId !== null) {
        $headers[] = 'PayPal-Request-Id: ' . $requestId;
    }
    $curl = curl_init($baseUrl . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        error_log('TradeHub PayPal API request failed for ' . $path . '. HTTP ' . $status . ($curlError !== '' ? ': ' . $curlError : ''));
        throw new RuntimeException('PayPal could not complete this payment. Please try again or choose another method.');
    }

    $result = json_decode($body, true);
    if (!is_array($result)) {
        throw new RuntimeException('PayPal returned an invalid response.');
    }

    return $result;
}

function paypal_create_order(string $amount, string $reference, int $userId): string
{
    $paypal = config()['paypal'];
    $result = paypal_api_request('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $reference,
            'custom_id' => (string) $userId,
            'amount' => ['currency_code' => $paypal['currency'], 'value' => $amount],
        ]],
        'application_context' => [
            'brand_name' => 'TradeHub',
            'user_action' => 'PAY_NOW',
        ],
    ]);

    if (!is_string($result['id'] ?? null) || $result['id'] === '') {
        throw new RuntimeException('PayPal did not return a valid order ID.');
    }

    return $result['id'];
}

/** @return array{capture_id: string, status: string} */
function paypal_capture_order(string $orderId, string $expectedAmount, int $userId, string $reference): array
{
    $paypal = config()['paypal'];
    $order = paypal_api_request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
    $purchaseUnit = $order['purchase_units'][0] ?? [];
    $amount = $purchaseUnit['amount'] ?? [];
    if (count($order['purchase_units'] ?? []) !== 1
        || ($purchaseUnit['reference_id'] ?? null) !== $reference
        || ($purchaseUnit['custom_id'] ?? null) !== (string) $userId
        || ($amount['currency_code'] ?? null) !== $paypal['currency']
        || !is_string($amount['value'] ?? null)
        || number_format((float) $amount['value'], 2, '.', '') !== $expectedAmount) {
        throw new RuntimeException('The PayPal order amount does not match your checkout total.');
    }

    if (($order['status'] ?? null) !== 'COMPLETED') {
        if (($order['status'] ?? null) !== 'APPROVED') {
            throw new RuntimeException('The PayPal payment was not approved.');
        }
        $order = paypal_api_request(
            'POST',
            '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            null,
            'capture-' . $orderId
        );
    }

    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? [];
    if (($order['status'] ?? null) !== 'COMPLETED'
        || ($capture['status'] ?? null) !== 'COMPLETED'
        || !is_string($capture['id'] ?? null)
        || $capture['id'] === '') {
        throw new RuntimeException('PayPal has not completed this payment.');
    }

    return ['capture_id' => $capture['id'], 'status' => $capture['status']];
}

function paypal_refund_capture(string $captureId): string
{
    $result = paypal_api_request(
        'POST',
        '/v2/payments/captures/' . rawurlencode($captureId) . '/refund',
        null,
        'refund-' . $captureId
    );

    return (string) ($result['status'] ?? 'PENDING');
}
