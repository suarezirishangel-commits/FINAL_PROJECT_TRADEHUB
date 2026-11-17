<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerReview;
use App\Models\Transaction;
use App\Support\TradeHubContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(TradeHubContent $content): View
    {
        $stats = [
            'products' => Product::query()->where('is_active', true)->count(),
            'categories' => Category::query()->count(),
            'orders' => Transaction::query()
                ->where('status', '<>', 'cancelled')
                ->distinct('reference')
                ->count('reference'),
        ];

        $featured = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderByRaw('stock > 0 DESC')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('storefront.home', [
            'stats' => $stats,
            'featured' => $featured,
            'announcements' => collect($content->announcements())->take(3),
        ]);
    }

    public function products(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'integer', 'min:1'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'available' => ['nullable', 'in:1'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name'],
        ]);

        $query = Product::query()
            ->with('category')
            ->where('is_active', true);

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (($filters['available'] ?? '') === '1') {
            $query->where('stock', '>', 0);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $products = $query->paginate(9)->withQueryString();

        return view('storefront.products', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'filters' => array_merge([
                'q' => '',
                'category' => '',
                'min_price' => '',
                'max_price' => '',
                'available' => '',
                'sort' => 'newest',
            ], $filters),
            'activeCategory' => Category::query()->find($filters['category'] ?? null),
        ]);
    }

    public function showProduct(int $id): View
    {
        $product = Product::query()
            ->with(['category', 'seller'])
            ->where('is_active', true)
            ->findOrFail($id);

        $related = Product::query()
            ->with('category')
            ->where('category_id', $product->category_id)
            ->where('id', '<>', $product->id)
            ->where('is_active', true)
            ->orderByRaw('stock > 0 DESC')
            ->orderBy('name')
            ->limit(3)
            ->get();

        $productReviews = ProductReview::query()
            ->with('reviewer')
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->paginate(5);
        $productRatingAverage = ProductReview::query()
            ->where('product_id', $product->id)
            ->avg('rating');
        $productRatingCount = ProductReview::query()
            ->where('product_id', $product->id)
            ->count();
        $sellerRatingAverage = $product->seller_id
            ? SellerReview::query()->where('seller_id', $product->seller_id)->avg('rating')
            : null;
        $sellerRatingCount = $product->seller_id
            ? SellerReview::query()->where('seller_id', $product->seller_id)->count()
            : 0;

        return view('storefront.product', compact(
            'product',
            'related',
            'productReviews',
            'productRatingAverage',
            'productRatingCount',
            'sellerRatingAverage',
            'sellerRatingCount',
        ));
    }
}
