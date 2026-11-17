<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalGateway
{
    public function isConfigured(): bool
    {
        $config = config('services.paypal');

        return in_array($config['mode'] ?? null, ['sandbox', 'live'], true)
            && is_string($config['client_id'] ?? null)
            && $config['client_id'] !== ''
            && is_string($config['client_secret'] ?? null)
            && $config['client_secret'] !== ''
            && is_string($config['currency'] ?? null)
            && preg_match('/^[A-Z]{3}$/', $config['currency']) === 1;
    }

    public function clientId(): string
    {
        return (string) config('services.paypal.client_id', '');
    }

    public function currency(): string
    {
        return (string) config('services.paypal.currency', 'PHP');
    }

    public function createOrder(string $amount, string $reference, int $userId): string
    {
        $result = $this->request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'custom_id' => (string) $userId,
                'amount' => [
                    'currency_code' => $this->currency(),
                    'value' => $amount,
                ],
            ]],
            'application_context' => [
                'brand_name' => config('app.name', 'TradeHub'),
                'user_action' => 'PAY_NOW',
            ],
        ]);

        if (! is_string($result['id'] ?? null) || $result['id'] === '') {
            throw new RuntimeException('PayPal did not return a valid order ID.');
        }

        return $result['id'];
    }

    /**
     * @return array{capture_id: string, status: string}
     */
    public function captureOrder(string $orderId, string $expectedAmount, int $userId, string $reference): array
    {
        $order = $this->request('GET', '/v2/checkout/orders/'.rawurlencode($orderId));
        $purchaseUnit = $order['purchase_units'][0] ?? [];
        $amount = $purchaseUnit['amount'] ?? [];

        if (count($order['purchase_units'] ?? []) !== 1
            || ($purchaseUnit['reference_id'] ?? null) !== $reference
            || ($purchaseUnit['custom_id'] ?? null) !== (string) $userId
            || ($amount['currency_code'] ?? null) !== $this->currency()
            || ! is_string($amount['value'] ?? null)
            || number_format((float) $amount['value'], 2, '.', '') !== $expectedAmount) {
            throw new RuntimeException('The PayPal order amount does not match your checkout total.');
        }

        if (($order['status'] ?? null) === 'COMPLETED') {
            return $this->captureDetails($order);
        }

        if (($order['status'] ?? null) !== 'APPROVED') {
            throw new RuntimeException('The PayPal payment was not approved.');
        }

        $captured = $this->request(
            'POST',
            '/v2/checkout/orders/'.rawurlencode($orderId).'/capture',
            null,
            'capture-'.$orderId
        );

        if (($captured['status'] ?? null) !== 'COMPLETED') {
            throw new RuntimeException('PayPal has not completed this payment.');
        }

        return $this->captureDetails($captured);
    }

    public function refundCapture(string $captureId): string
    {
        $result = $this->request(
            'POST',
            '/v2/payments/captures/'.rawurlencode($captureId).'/refund',
            null,
            'refund-'.$captureId
        );

        return (string) ($result['status'] ?? 'PENDING');
    }

    /**
     * @return array{capture_id: string, status: string}
     */
    private function captureDetails(array $order): array
    {
        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? [];
        if (($capture['status'] ?? null) !== 'COMPLETED'
            || ! is_string($capture['id'] ?? null)
            || $capture['id'] === '') {
            throw new RuntimeException('PayPal did not return a completed capture.');
        }

        return ['capture_id' => $capture['id'], 'status' => $capture['status']];
    }

    private function request(string $method, string $path, ?array $payload = null, ?string $requestId = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PayPal is not configured. Add the PayPal credentials to the server environment.');
        }

        $baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        try {
            $tokenResponse = Http::asForm()
                ->withBasicAuth($this->clientId(), (string) config('services.paypal.client_secret'))
                ->timeout(20)
                ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $exception) {
            Log::error('PayPal OAuth request failed.', ['message' => $exception->getMessage()]);
            throw new RuntimeException('PayPal is temporarily unavailable. Please try again.', previous: $exception);
        }

        if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
            Log::error('PayPal OAuth returned an error.', ['status' => $tokenResponse->status()]);
            throw new RuntimeException('PayPal could not authorize this payment.');
        }

        $request = Http::acceptJson()
            ->withToken($tokenResponse->json('access_token'))
            ->timeout(20);

        if ($requestId !== null) {
            $request = $request->withHeaders(['PayPal-Request-Id' => $requestId]);
        }

        try {
            $response = match ($method) {
                'GET' => $request->get($baseUrl.$path),
                default => $payload === null
                    ? $request->send($method, $baseUrl.$path)
                    : $request->post($baseUrl.$path, $payload),
            };
        } catch (ConnectionException $exception) {
            Log::error('PayPal API request failed.', ['path' => $path, 'message' => $exception->getMessage()]);
            throw new RuntimeException('PayPal is temporarily unavailable. Please try again.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('PayPal API returned an error.', ['path' => $path, 'status' => $response->status()]);
            throw new RuntimeException('PayPal could not complete this payment. Please try again or choose another method.');
        }

        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('PayPal returned an invalid response.');
        }

        return $result;
    }
}
