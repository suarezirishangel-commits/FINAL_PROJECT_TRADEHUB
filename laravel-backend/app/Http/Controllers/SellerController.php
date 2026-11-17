<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerReview;
use App\Models\User;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function show(int $id): View
    {
        $seller = User::query()
            ->whereKey($id)
            ->whereHas('role', fn ($query) => $query->where('name', 'Seller'))
            ->firstOrFail();
        $products = Product::query()
            ->with('category')
            ->where('seller_id', $seller->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $reviews = SellerReview::query()
            ->with('reviewer')
            ->where('seller_id', $seller->id)
            ->orderByDesc('created_at')
            ->paginate(10);
        $ratingAverage = SellerReview::query()->where('seller_id', $seller->id)->avg('rating');
        $ratingCount = SellerReview::query()->where('seller_id', $seller->id)->count();

        return view('storefront.seller', compact('seller', 'products', 'reviews', 'ratingAverage', 'ratingCount'));
    }
}
