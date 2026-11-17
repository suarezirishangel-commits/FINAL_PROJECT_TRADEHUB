<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Services\PayPalGateway;
use App\Support\OrderConfirmationSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalController extends Controller
{
    public function createOrder(Request $request, PayPalGateway $paypal): JsonResponse
    {
        if (! $paypal->isConfigured()) {
            return response()->json([
                'message' => 'PayPal is not configured. Add the PayPal credentials to the server environment.',
            ], 503);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
        ], ['phone.regex' => 'Please enter a valid contact number.']);

        $cart = $request->session()->get('cart', []);
        if (! is_array($cart) || $cart === []) {
            return response()->json(['message' => 'Your cart is empty. Add a product before checking out.'], 422);
        }

        $products = Product::query()
            ->whereIn('id', array_map('intval', array_keys($cart)))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
        $items = [];
        $total = 0.0;

        foreach ($cart as $id => $quantity) {
            $product = $products->get((int) $id);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if (! $product || $quantity === false || $quantity < 1) {
                continue;
            }
            if ($product->stock < $quantity) {
                return response()->json([
                    'message' => $product->name.' only has '.$product->stock.' left in stock.',
                ], 422);
            }

            $lineTotal = round($quantity * (float) $product->price, 2);
            $items[] = [
                'product_id' => $product->id,
                'seller_id' => $product->seller_id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => number_format((float) $product->price, 2, '.', ''),
                'total_amount' => number_format($lineTotal, 2, '.', ''),
            ];
            $total += $lineTotal;
        }

        if ($items === []) {
            return response()->json(['message' => 'The items in your cart are no longer available.'], 422);
        }

        $amount = number_format($total, 2, '.', '');
        $reference = 'TH-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        try {
            $paypalOrderId = $paypal->createOrder($amount, $reference, (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        DB::table('paypal_payments')->insert([
            'paypal_order_id' => $paypalOrderId,
            'user_id' => $request->user()->id,
            'amount' => $amount,
            'currency' => $paypal->currency(),
            'status' => 'created',
            'checkout_data' => json_encode([
                'reference' => $reference,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'items' => $items,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $paypalOrderId]);
    }

    public function captureOrder(Request $request, PayPalGateway $paypal): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string', 'max:64'],
        ]);

        $payment = DB::table('paypal_payments')
            ->where('paypal_order_id', $data['order_id'])
            ->where('user_id', $request->user()->id)
            ->first();
        if (! $payment) {
            return response()->json(['message' => 'PayPal order not found.'], 404);
        }
        if ($payment->status === 'completed') {
            return response()->json([
                'redirect_url' => route('orders', ['ref' => $payment->reference]),
            ]);
        }
        if (! in_array($payment->status, ['created', 'captured'], true)) {
            return response()->json(['message' => 'This PayPal checkout is no longer available.'], 409);
        }

        $checkout = json_decode($payment->checkout_data, true, 512, JSON_THROW_ON_ERROR);
        try {
            $capture = $paypal->captureOrder(
                $payment->paypal_order_id,
                number_format((float) $payment->amount, 2, '.', ''),
                (int) $payment->user_id,
                (string) $checkout['reference']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        DB::table('paypal_payments')
            ->where('paypal_order_id', $payment->paypal_order_id)
            ->where('status', 'created')
            ->update([
                'status' => 'captured',
                'capture_id' => $capture['capture_id'],
                'updated_at' => now(),
            ]);

        $inventoryError = null;
        $reference = $checkout['reference'];

        $alreadyCompleted = false;
        DB::transaction(function () use ($payment, $checkout, $reference, $capture, &$inventoryError, &$alreadyCompleted): void {
            $lockedPayment = DB::table('paypal_payments')
                ->where('paypal_order_id', $payment->paypal_order_id)
                ->lockForUpdate()
                ->first();
            if ($lockedPayment->status === 'completed') {
                $alreadyCompleted = true;
                return;
            }

            $items = $checkout['items'];
            $products = Product::query()
                ->whereIn('id', array_column($items, 'product_id'))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get((int) $item['product_id']);
                if (! $product || $product->stock < (int) $item['quantity']) {
                    $inventoryError = $product
                        ? $product->name.' no longer has enough stock.'
                        : $item['product_name'].' is no longer available.';

                    return;
                }
            }

            foreach ($items as $item) {
                Transaction::query()->create([
                    'reference' => $reference,
                    'user_id' => $payment->user_id,
                    'product_id' => $item['product_id'],
                    'seller_id' => $item['seller_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['total_amount'],
                    'payment_method' => 'paypal',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'created_at' => now(),
                ]);
                $products->get((int) $item['product_id'])->decrement('stock', (int) $item['quantity']);
            }

            DB::table('order_delivery_tracking')->insert([
                'reference' => $reference,
                'delivery_address' => $checkout['address'],
            ]);
            DB::table('paypal_payments')
                ->where('paypal_order_id', $payment->paypal_order_id)
                ->update([
                    'reference' => $reference,
                    'status' => 'completed',
                    'capture_id' => $capture['capture_id'],
                    'updated_at' => now(),
                ]);
        });

        if ($inventoryError !== null) {
            try {
                $refundStatus = $paypal->refundCapture($capture['capture_id']);
                DB::table('paypal_payments')
                    ->where('paypal_order_id', $payment->paypal_order_id)
                    ->update([
                        'status' => $refundStatus === 'COMPLETED' ? 'refunded' : 'refund_pending',
                        'updated_at' => now(),
                    ]);
                $message = $refundStatus === 'COMPLETED'
                    ? 'The item became unavailable after payment. PayPal has refunded your payment.'
                    : 'The item became unavailable after payment. Your PayPal refund is being processed.';
            } catch (RuntimeException $exception) {
                Log::error('PayPal refund could not be completed.', [
                    'order_id' => $payment->paypal_order_id,
                    'capture_id' => $capture['capture_id'],
                    'message' => $exception->getMessage(),
                ]);
                DB::table('paypal_payments')
                    ->where('paypal_order_id', $payment->paypal_order_id)
                    ->update(['status' => 'refund_pending', 'updated_at' => now()]);
                $message = 'The item became unavailable after payment. Contact TradeHub support with PayPal order ID '.$payment->paypal_order_id.' to complete your refund.';
            }

            return response()->json(['message' => $message], 409);
        }

        if ($alreadyCompleted) {
            return response()->json([
                'redirect_url' => route('orders', ['ref' => $payment->reference]),
            ]);
        }

        $request->session()->put('cart', []);
        $mailUser = clone $request->user();
        $mailUser->full_name = $checkout['full_name'];
        $mailUser->phone = $checkout['phone'];
        $mailUser->address = $checkout['address'];
        $items = array_map(fn (array $item): array => [
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_amount' => $item['total_amount'],
        ], $checkout['items']);
        $total = array_sum(array_map(
            fn (array $item): float => (float) $item['total_amount'],
            $checkout['items']
        ));
        $mailed = app(OrderConfirmationSender::class)->send($mailUser, $reference, $items, $total, 'paypal');

        return response()->json([
            'redirect_url' => route('orders', ['ref' => $reference]),
            'message' => 'Order '.$reference.' paid successfully'.($mailed ? '.' : '. The email confirmation could not be sent right now.'),
        ]);
    }
}
