<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerReview;
use App\Models\Transaction;
use App\Services\PayPalGateway;
use App\Support\OrderConfirmationSender;
use App\Support\TradeHubPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['phone.regex' => 'Enter a valid contact number.']);

        $request->user()->update([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
        ]);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'same:confirm_password'],
            'confirm_password' => ['required', 'string'],
        ], [
            'new_password.regex' => 'Password must contain at least one letter and one number.',
            'new_password.same' => 'New passwords do not match.',
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password_hash)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $request->user()->update(['password_hash' => Hash::make($data['new_password'])]);

        return back()->with('success', 'Password changed.');
    }

    public function orders(Request $request): View
    {
        $rows = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $deliveries = DB::table('order_delivery_tracking')
            ->leftJoin('users as riders', 'riders.id', '=', 'order_delivery_tracking.rider_id')
            ->whereIn('order_delivery_tracking.reference', $rows->pluck('reference')->unique())
            ->select([
                'order_delivery_tracking.reference',
                'order_delivery_tracking.rider_id',
                'order_delivery_tracking.delivery_started_at',
                'order_delivery_tracking.latitude',
                'order_delivery_tracking.longitude',
                'order_delivery_tracking.location_updated_at',
                'riders.full_name as rider_name',
            ])
            ->get()
            ->keyBy('reference');
        $references = $rows->pluck('reference')->unique();
        $productReviews = ProductReview::query()
            ->where('reviewer_id', $request->user()->id)
            ->whereIn('reference', $references)
            ->get()
            ->groupBy('reference')
            ->map(fn ($reviews) => $reviews->keyBy('product_id'));
        $sellerReviews = SellerReview::query()
            ->where('reviewer_id', $request->user()->id)
            ->whereIn('reference', $references)
            ->get()
            ->groupBy('reference')
            ->map(fn ($reviews) => $reviews->keyBy('seller_id'));

        $orders = $rows->groupBy('reference')->map(function ($items, string $reference) use ($deliveries, $productReviews, $sellerReviews): array {
            $first = $items->first();
            $delivery = $deliveries->get($reference);
            $status = $items->sortBy(fn (Transaction $item) => TradeHubPresenter::orderStatusRank($item->status))->first()->status;

            return [
                'reference' => $reference,
                'created_at' => $first->created_at,
                'status' => $status,
                'payment_method' => $first->payment_method,
                'tracking_number' => $first->tracking_number,
                'paid_at' => $first->paid_at,
                'completed_at' => $first->completed_at,
                'cancelled_at' => $first->cancelled_at,
                'delivery_started_at' => $delivery?->delivery_started_at
                    ? Carbon::parse($delivery->delivery_started_at)
                    : null,
                'rider_name' => $delivery?->rider_name,
                'rider_assigned' => $delivery?->rider_id !== null,
                'latitude' => $delivery?->latitude === null ? null : (float) $delivery->latitude,
                'longitude' => $delivery?->longitude === null ? null : (float) $delivery->longitude,
                'location_updated_at' => $delivery?->location_updated_at
                    ? Carbon::parse($delivery->location_updated_at)
                    : null,
                'product_reviews' => $productReviews->get($reference, collect()),
                'seller_reviews' => $sellerReviews->get($reference, collect()),
                'total' => $items->sum('total_amount'),
                'items' => $items,
            ];
        });

        return view('account.orders', [
            'orders' => $orders,
            'highlight' => (string) $request->query('ref', ''),
        ]);
    }

    public function deliveryLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
        ]);
        $order = DB::table('transactions as t')
            ->leftJoin('order_delivery_tracking as d', 'd.reference', '=', 't.reference')
            ->where('t.reference', $data['reference'])
            ->where('t.user_id', $request->user()->id)
            ->orderBy('t.id')
            ->first([
                't.status',
                'd.rider_id',
                'd.latitude',
                'd.longitude',
                'd.location_updated_at',
            ]);

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'status' => $order->status,
            'rider_assigned' => $order->rider_id !== null,
            'latitude' => $order->status === 'cancelled' || $order->latitude === null
                ? null
                : (float) $order->latitude,
            'longitude' => $order->status === 'cancelled' || $order->longitude === null
                ? null
                : (float) $order->longitude,
            'location_updated_at' => $order->location_updated_at
                ? Carbon::parse($order->location_updated_at)->toIso8601String()
                : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function checkoutForm(Request $request, PayPalGateway $paypal): View|RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        if (! $cart) {
            return redirect()->route('products')->with('info', 'Your cart is empty. Add a product before checking out.');
        }

        $items = Product::query()
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->get()
            ->map(function (Product $product) use ($cart): ?array {
                $quantity = min((int) $cart[$product->id], (int) $product->stock);

                return $quantity > 0
                    ? ['product' => $product, 'quantity' => $quantity, 'line_total' => $quantity * (float) $product->price]
                    : null;
            })->filter()->values();

        if ($items->isEmpty()) {
            return redirect()->route('cart')->with('error', 'The items in your cart are no longer available.');
        }

        return view('storefront.checkout', [
            'items' => $items,
            'total' => $items->sum('line_total'),
            'user' => $request->user(),
            'paypalClientId' => $paypal->isConfigured() ? $paypal->clientId() : null,
            'paypalCurrency' => $paypal->currency(),
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'payment_method' => ['required', 'in:cod'],
        ], ['phone.regex' => 'Please enter a valid contact number.']);

        $cart = $request->session()->get('cart', []);
        if (! $cart) {
            return redirect()->route('products')->with('info', 'Your cart is empty. Add a product before checking out.');
        }

        $reference = 'TH-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        $user = $request->user();
        $items = [];
        $total = 0.0;

        try {
            DB::transaction(function () use ($cart, $data, $user, $reference, &$items, &$total): void {
                $products = Product::query()
                    ->whereIn('id', array_keys($cart))
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($cart as $id => $quantity) {
                    $product = $products->get((int) $id);
                    $quantity = (int) $quantity;

                    if (! $product || $quantity < 1) {
                        continue;
                    }

                    if ($product->stock < $quantity) {
                        throw new RuntimeException($product->name.' only has '.$product->stock.' left in stock.');
                    }

                    $lineTotal = $quantity * (float) $product->price;
                    $items[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'total_amount' => $lineTotal,
                    ];
                    $total += $lineTotal;

                    Transaction::query()->create([
                        'reference' => $reference,
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'seller_id' => $product->seller_id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'total_amount' => $lineTotal,
                        'payment_method' => $data['payment_method'],
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);

                    $product->decrement('stock', $quantity);
                }

                if ($items === []) {
                    throw new RuntimeException('The items in your cart are no longer available.');
                }

                DB::table('order_delivery_tracking')->insert([
                    'reference' => $reference,
                    'delivery_address' => $data['address'],
                ]);
            });
        } catch (RuntimeException $error) {
            return redirect()->route('checkout')->withErrors(['cart' => $error->getMessage()]);
        }

        $request->session()->put('cart', []);

        $mailUser = clone $user;
        $mailUser->full_name = $data['full_name'];
        $mailUser->phone = $data['phone'];
        $mailUser->address = $data['address'];
        $mailed = app(OrderConfirmationSender::class)->send($mailUser, $reference, $items, $total, $data['payment_method']);

        return redirect()->route('orders', ['ref' => $reference])->with(
            'success',
            'Order '.$reference.' placed successfully'
                .($mailed ? '. A confirmation email is on its way.' : '. The email confirmation could not be sent right now.')
        );
    }
}
