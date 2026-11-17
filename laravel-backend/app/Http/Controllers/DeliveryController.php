<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\TradeHubPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $riderId = $request->user()->id;
        $rows = Transaction::query()
            ->with('user')
            ->whereIn('reference', DB::table('order_delivery_tracking')
                ->select('reference')
                ->where('rider_id', $riderId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $deliveries = DB::table('order_delivery_tracking')
            ->whereIn('reference', $rows->pluck('reference')->unique())
            ->get()
            ->keyBy('reference');

        $orders = $rows->groupBy('reference')->map(function ($items, string $reference) use ($deliveries): array {
            $first = $items->first();
            $delivery = $deliveries->get($reference);
            $status = $items->sortBy(
                fn (Transaction $item) => TradeHubPresenter::orderStatusRank($item->status)
            )->first()->status;

            return [
                'reference' => $reference,
                'status' => $status,
                'payment_method' => $first->payment_method,
                'paid_at' => $first->paid_at,
                'created_at' => $first->created_at,
                'customer' => $first->user->full_name,
                'phone' => $first->user->phone,
                'delivery_address' => $delivery?->delivery_address ?: $first->user->address,
                'delivery_started_at' => $delivery?->delivery_started_at
                    ? Carbon::parse($delivery->delivery_started_at)
                    : null,
                'latitude' => $delivery?->latitude === null ? null : (float) $delivery->latitude,
                'longitude' => $delivery?->longitude === null ? null : (float) $delivery->longitude,
                'location_updated_at' => $delivery?->location_updated_at
                    ? Carbon::parse($delivery->location_updated_at)
                    : null,
                'tracking_number' => $first->tracking_number,
                'total' => $items->sum('total_amount'),
                'items' => $items,
            ];
        });

        return view('delivery.orders', compact('orders'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'action' => ['required', 'in:start,collect_payment,complete'],
        ]);
        $riderId = $request->user()->id;
        $now = now();

        $error = DB::transaction(function () use ($data, $riderId, $now): ?string {
            $orders = DB::table('transactions')
                ->where('reference', $data['reference'])
                ->lockForUpdate()
                ->get(['status', 'payment_method', 'paid_at']);

            if ($orders->isEmpty()) {
                abort(404);
            }

            $delivery = DB::table('order_delivery_tracking')
                ->where('reference', $data['reference'])
                ->where('rider_id', $riderId)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                abort(404);
            }

            $order = $orders->first();
            if ($data['action'] === 'start') {
                if (! in_array($order->status, ['pending', 'paid'], true)) {
                    return 'This order cannot be started from its current status.';
                }

                DB::table('transactions')
                    ->where('reference', $data['reference'])
                    ->update(['status' => 'out_for_delivery']);
                DB::table('order_delivery_tracking')
                    ->where('reference', $data['reference'])
                    ->update(['delivery_started_at' => $delivery->delivery_started_at ?: $now]);

                return null;
            }

            if ($data['action'] === 'collect_payment') {
                if ($order->payment_method !== 'cod' || $order->status !== 'out_for_delivery' || $order->paid_at !== null) {
                    return 'COD payment cannot be collected for this order in its current state.';
                }

                DB::table('transactions')
                    ->where('reference', $data['reference'])
                    ->whereNull('paid_at')
                    ->update(['paid_at' => $now]);

                return null;
            }

            if ($order->status !== 'out_for_delivery'
                || ($order->payment_method === 'cod' && $order->paid_at === null)) {
                return 'For COD orders, record the payment before marking the delivery complete.';
            }

            DB::table('transactions')
                ->where('reference', $data['reference'])
                ->update([
                    'status' => 'completed',
                    'completed_at' => $now,
                ]);

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        $message = match ($data['action']) {
            'start' => 'Delivery started.',
            'collect_payment' => 'COD payment marked as collected.',
            default => 'Order marked as delivered.',
        };

        return back()->with('success', $message);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'latitude' => ['required', 'numeric', 'between:4.2,21.8'],
            'longitude' => ['required', 'numeric', 'between:115.5,128'],
        ], [
            'latitude.between' => 'The rider location must be within the Philippines.',
            'longitude.between' => 'The rider location must be within the Philippines.',
        ]);
        $riderId = $request->user()->id;
        $updatedAt = now();

        DB::transaction(function () use ($data, $riderId, $updatedAt): void {
            $order = DB::table('transactions')
                ->where('reference', $data['reference'])
                ->where('status', 'out_for_delivery')
                ->lockForUpdate()
                ->first(['id']);

            if (! $order) {
                abort(404);
            }

            $delivery = DB::table('order_delivery_tracking')
                ->where('reference', $data['reference'])
                ->where('rider_id', $riderId)
                ->lockForUpdate()
                ->first(['reference']);

            if (! $delivery) {
                abort(404);
            }

            DB::table('order_delivery_tracking')
                ->where('reference', $data['reference'])
                ->where('rider_id', $riderId)
                ->update([
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'location_updated_at' => $updatedAt,
                ]);
        });

        return response()->json([
            'ok' => true,
            'updated_at' => $updatedAt->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
