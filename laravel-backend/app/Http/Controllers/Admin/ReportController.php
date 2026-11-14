<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\TradeHubContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request, TradeHubContent $content): View
    {
        $dates = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->toDateString();
        $range = [$from.' 00:00:00', $to.' 23:59:59'];

        $query = Transaction::query()
            ->where('status', '<>', 'cancelled')
            ->whereBetween('created_at', $range);
        $gross = (float) (clone $query)->sum('total_amount');
        $ordersCount = (clone $query)->distinct('reference')->count('reference');
        $units = (int) (clone $query)->sum('quantity');
        $rate = (float) $content->setting('commission_rate', '0');

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'gross' => $gross,
                'orders' => $ordersCount,
                'units' => $units,
            ],
            'rate' => $rate,
            'commission' => $gross * $rate / 100,
            'avgOrder' => $ordersCount > 0 ? $gross / $ordersCount : 0,
            'categoryRows' => DB::table('transactions as t')
                ->join('products as p', 'p.id', '=', 't.product_id')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->where('t.status', '<>', 'cancelled')
                ->whereBetween('t.created_at', $range)
                ->groupBy('c.id', 'c.name')
                ->select('c.name', DB::raw('SUM(t.total_amount) as revenue'), DB::raw('SUM(t.quantity) as units'))
                ->orderByDesc('revenue')
                ->get(),
            'topRows' => DB::table('transactions')
                ->where('status', '<>', 'cancelled')
                ->whereBetween('created_at', $range)
                ->groupBy('product_name')
                ->select('product_name', DB::raw('SUM(quantity) as units'), DB::raw('SUM(total_amount) as revenue'))
                ->orderByDesc('revenue')
                ->limit(5)
                ->get(),
            'logRows' => Transaction::query()
                ->with('user')
                ->whereBetween('created_at', $range)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }
}
