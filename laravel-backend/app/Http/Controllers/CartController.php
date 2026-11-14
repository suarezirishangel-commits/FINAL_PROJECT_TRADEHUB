<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $request->session()->get('cart', []);
        $products = Product::query()
            ->with('category')
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->get();

        $items = $products->map(function (Product $product) use ($cart): ?array {
            $quantity = min((int) ($cart[$product->id] ?? 0), (int) $product->stock);
            if ($quantity < 1) {
                return null;
            }

            return [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => $quantity * (float) $product->price,
            ];
        })->filter()->values();

        return view('storefront.cart', [
            'items' => $items,
            'total' => $items->sum('line_total'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:add,update,remove'],
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);
        $product = Product::query()
            ->where('is_active', true)
            ->find($data['product_id']);

        if (! $product) {
            return redirect()->route('products')->with('error', 'That product is no longer available.');
        }

        $cart = $request->session()->get('cart', []);
        $id = (int) $product->id;

        if ($data['action'] === 'remove') {
            unset($cart[$id]);
            $message = 'Item removed from your cart.';
        } elseif ((int) $product->stock < 1) {
            unset($cart[$id]);
            $message = $product->name.' is out of stock.';
            $request->session()->put('cart', $cart);

            return back()->with('error', $message);
        } else {
            $requested = (int) ($data['quantity'] ?? 1);
            $quantity = $data['action'] === 'add'
                ? (int) ($cart[$id] ?? 0) + $requested
                : $requested;
            $cart[$id] = min($quantity, (int) $product->stock);
            $message = $data['action'] === 'add'
                ? $product->name.' was added to your cart.'
                : 'Cart updated.';
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart')->with('success', $message);
    }
}
