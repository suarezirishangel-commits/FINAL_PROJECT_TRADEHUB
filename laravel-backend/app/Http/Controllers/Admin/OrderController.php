<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\TradeHubPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $rows = Transaction::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $orders = $rows->groupBy('reference')->map(function ($items, string $reference): array {
            $first = $items->first();

            return [
                'reference' => $reference,
                'customer' => $first->user->full_name,
                'email' => $first->user->email,
                'created_at' => $first->created_at,
                'status' => $items->sortBy(fn (Transaction $item) => TradeHubPresenter::orderStatusRank($item->status))->first()->status,
                'tracking_number' => $first->tracking_number,
                'total' => $items->sum('total_amount'),
                'items' => $items,
            ];
        });

        return view('admin.orders', compact('orders'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20', 'exists:transactions,reference'],
            'status' => ['required', 'in:pending,paid,completed,cancelled'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
        ]);
        $changes = [
            'status' => $data['status'],
            'tracking_number' => $data['tracking_number'] ?: null,
        ];
        $now = now();

        if (in_array($data['status'], ['paid', 'completed'], true)) {
            DB::table('transactions')
                ->where('reference', $data['reference'])
                ->whereNull('paid_at')
                ->update(['paid_at' => $now]);
        }
        if ($data['status'] === 'completed') {
            DB::table('transactions')
                ->where('reference', $data['reference'])
                ->whereNull('completed_at')
                ->update(['completed_at' => $now]);
        }
        if ($data['status'] === 'cancelled') {
            DB::table('transactions')
                ->where('reference', $data['reference'])
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => $now]);
        }

        DB::table('transactions')
            ->where('reference', $data['reference'])
            ->update($changes);

        return back()->with('success', 'Order '.$data['reference'].' updated.');
    }
}
