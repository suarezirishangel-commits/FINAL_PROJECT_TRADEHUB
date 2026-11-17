<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TradeHubPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $deliveries = DB::table('order_delivery_tracking')
            ->leftJoin('users as riders', 'riders.id', '=', 'order_delivery_tracking.rider_id')
            ->whereIn('order_delivery_tracking.reference', $rows->pluck('reference')->unique())
            ->select([
                'order_delivery_tracking.reference',
                'order_delivery_tracking.rider_id',
                'order_delivery_tracking.delivery_started_at',
                'riders.full_name as rider_name',
            ])
            ->get()
            ->keyBy('reference');

        $orders = $rows->groupBy('reference')->map(function ($items, string $reference) use ($deliveries): array {
            $first = $items->first();
            $delivery = $deliveries->get($reference);

            return [
                'reference' => $reference,
                'customer' => $first->user->full_name,
                'email' => $first->user->email,
                'created_at' => $first->created_at,
                'status' => $items->sortBy(fn (Transaction $item) => TradeHubPresenter::orderStatusRank($item->status))->first()->status,
                'tracking_number' => $first->tracking_number,
                'rider_id' => $delivery?->rider_id,
                'rider_name' => $delivery?->rider_name,
                'delivery_started_at' => $delivery?->delivery_started_at
                    ? Carbon::parse($delivery->delivery_started_at)
                    : null,
                'total' => $items->sum('total_amount'),
                'items' => $items,
            ];
        });

        $riders = User::query()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'Delivery Rider'))
            ->orderBy('full_name')
            ->get();

        return view('admin.orders', compact('orders', 'riders'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20', 'exists:transactions,reference'],
            'status' => ['required', 'in:pending,paid,out_for_delivery,completed,cancelled'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
            'rider_id' => ['nullable', 'required_if:status,out_for_delivery', 'integer', 'exists:users,id'],
        ]);
        if (! empty($data['rider_id']) && ! User::query()
            ->whereKey($data['rider_id'])
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'Delivery Rider'))
            ->exists()) {
            return back()
                ->withErrors(['rider_id' => 'Select an active delivery rider.'])
                ->withInput();
        }

        $changes = [
            'status' => $data['status'],
            'tracking_number' => $data['tracking_number'] ?: null,
        ];
        $now = now();

        DB::transaction(function () use ($data, $changes, $now): void {
            $deliveryStartedAt = DB::table('order_delivery_tracking')
                ->where('reference', $data['reference'])
                ->value('delivery_started_at');

            if ($data['status'] === 'out_for_delivery' && $deliveryStartedAt === null) {
                $deliveryStartedAt = $now;
            }

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

            DB::table('order_delivery_tracking')->updateOrInsert(
                ['reference' => $data['reference']],
                [
                    'rider_id' => $data['rider_id'] ?? null,
                    'delivery_started_at' => $deliveryStartedAt,
                ]
            );
        });

        return back()->with('success', 'Order '.$data['reference'].' updated.');
    }
}
