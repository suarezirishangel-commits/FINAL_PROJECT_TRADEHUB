<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TradeHubContent;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(TradeHubContent $content): View
    {
        $revenue = Transaction::query()->where('status', '<>', 'cancelled')->sum('total_amount');
        $totals = [
            'revenue' => $revenue,
            'orders' => Transaction::query()->distinct('reference')->count('reference'),
            'pending_lines' => Transaction::query()->where('status', 'pending')->count(),
        ];
        $counts = [
            'products' => Product::query()->where('is_active', true)->count(),
            'customers' => User::query()->whereHas('role', fn ($query) => $query->where('name', 'Customer'))->count(),
        ];
        $rate = (float) $content->setting('commission_rate', '0');
        $commission = (float) $revenue * $rate / 100;

        return view('admin.dashboard', [
            'totals' => $totals,
            'counts' => $counts,
            'rate' => $rate,
            'commission' => $commission,
            'recent' => Transaction::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'lowStock' => Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('stock', '<=', 10)
                ->orderBy('stock')
                ->orderBy('name')
                ->limit(6)
                ->get(),
        ]);
    }
}
