<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Support\OrderConfirmationSender;
use App\Support\TradeHubPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $orders = $rows->groupBy('reference')->map(function ($items, string $reference): array {
            $first = $items->first();
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
                'total' => $items->sum('total_amount'),
                'items' => $items,
            ];
        });

        return view('account.orders', [
            'orders' => $orders,
            'highlight' => (string) $request->query('ref', ''),
        ]);
    }

    public function checkoutForm(Request $request): View|RedirectResponse
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
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,20}$/'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'payment_method' => ['required', 'in:cod,gcash'],
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
