<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\SellerReview;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', 'in:product,seller'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $transaction = Transaction::query()
                ->where('reference', $data['reference'])
                ->where('product_id', $data['product_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($transaction->seller_id !== $request->user()->id, 403);

            if ($data['type'] === 'product') {
                ProductReview::query()->updateOrCreate(
                    [
                        'product_id' => $transaction->product_id,
                        'reviewer_id' => $request->user()->id,
                        'reference' => $transaction->reference,
                    ],
                    [
                        'rating' => $data['rating'],
                        'comment' => ($data['comment'] ?? '') ?: null,
                    ]
                );

                return;
            }

            abort_unless($transaction->seller_id !== null, 403);
            SellerReview::query()->updateOrCreate(
                [
                    'seller_id' => $transaction->seller_id,
                    'reviewer_id' => $request->user()->id,
                    'reference' => $transaction->reference,
                ],
                [
                    'rating' => $data['rating'],
                    'comment' => ($data['comment'] ?? '') ?: null,
                ]
            );
        });

        return back()->with('success', ucfirst($data['type']).' rating saved.');
    }
}
