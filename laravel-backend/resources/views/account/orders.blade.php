@extends('layouts.app', ['pageTitle' => 'My orders', 'active' => 'orders'])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Order history</div>
    <h1>My orders</h1>
    <p>Every checkout you have made, newest first, with its delivery progress.</p>
  </div>
  <div style="padding-bottom:3rem">
    @if ($orders->isEmpty())
      <div class="empty">
        <h3>No orders yet</h3>
        <p>Your completed checkouts will show up here.</p>
        <a class="btn btn-primary" href="{{ route('products') }}">Start shopping</a>
      </div>
    @endif
    @foreach ($orders as $order)
      @php
        $status = $order['status'];
        $steps = [
          ['label' => 'Order placed', 'at' => $order['created_at'], 'done' => true, 'cancelled' => false],
          ['label' => 'Payment confirmed', 'at' => $order['paid_at'], 'done' => $order['paid_at'] !== null || in_array($status, ['paid', 'completed'], true), 'cancelled' => false],
        ];
        if ($status === 'cancelled') {
          $steps[] = ['label' => 'Order cancelled', 'at' => $order['cancelled_at'], 'done' => true, 'cancelled' => true];
        } else {
          $steps[] = [
            'label' => 'Out for delivery',
            'at' => $order['delivery_started_at'],
            'done' => $order['delivery_started_at'] !== null || in_array($status, ['out_for_delivery', 'completed'], true),
            'cancelled' => false,
          ];
          $steps[] = ['label' => 'Order completed', 'at' => $order['completed_at'], 'done' => $order['completed_at'] !== null || $status === 'completed', 'cancelled' => false];
        }
      @endphp
      <article class="order-card" @if ($order['reference'] === $highlight) style="border-color:var(--brand);box-shadow:var(--shadow)" @endif>
        <div class="order-head">
          <div>
            <strong>{{ $order['reference'] }}</strong><br>
            <span class="hint">{{ $order['created_at']?->format('M j, Y · g:i A') }} · {{ strtoupper($order['payment_method']) }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:.8rem">
            <span class="badge badge-{{ str_replace('_', '-', $status) }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
            <strong>{{ \App\Support\TradeHubPresenter::price($order['total']) }}</strong>
          </div>
        </div>
        <ul class="order-items">
          @foreach ($order['items'] as $item)
            <li>
              <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
              <span>{{ \App\Support\TradeHubPresenter::price($item->total_amount) }}</span>
            </li>
          @endforeach
        </ul>
        <div class="order-track">
          <ol class="track-timeline">
            @foreach ($steps as $step)
              <li class="{{ $step['done'] ? 'is-done' : '' }}{{ $step['cancelled'] ? ' is-cancelled' : '' }}">
                <span class="track-dot" aria-hidden="true"></span>
                <strong>{{ $step['label'] }}</strong>
                <small>{{ $step['at'] ? $step['at']->format('M j, Y · g:i A') : ($step['done'] ? 'Done' : 'Waiting') }}</small>
              </li>
            @endforeach
          </ol>
          <div class="track-meta">
            @if ($order['rider_name'])
              <span>Delivery rider: <strong>{{ $order['rider_name'] }}</strong></span>
            @endif
            @if ($order['tracking_number'])
              <span>Tracking number: <strong>{{ $order['tracking_number'] }}</strong></span>
            @elseif ($status === 'cancelled')
              <span class="hint">This order was cancelled.</span>
            @else
              <span class="hint">No tracking number yet. It will appear here once the seller hands your order to the courier.</span>
            @endif
          </div>
          @if ($order['rider_assigned'])
            <section class="live-tracking"
                     data-delivery-map
                     data-mode="customer"
                     data-reference="{{ $order['reference'] }}"
                     data-endpoint="{{ route('orders.delivery-location') }}"
                     data-status="{{ $status }}"
                     data-latitude="{{ $order['latitude'] ?? '' }}"
                     data-longitude="{{ $order['longitude'] ?? '' }}"
                     data-location-updated-at="{{ $order['location_updated_at']?->toIso8601String() }}">
              <h3>Track delivery</h3>
              <p class="map-status" data-map-status aria-live="polite">Waiting for the rider’s GPS update.</p>
              <div class="delivery-map" data-map-canvas></div>
            </section>
          @endif
        </div>
        @if ($status === 'completed')
          <section class="order-reviews">
            <div class="section-head">
              <div><div class="kicker">Completed purchase</div><h2>Rate your purchase</h2></div>
            </div>
            @php($sellerReviewsShown = [])
            @foreach ($order['items'] as $item)
              @php($productReview = $order['product_reviews']->get($item->product_id))
              <div class="review-form-card">
                <h3>{{ $item->product_name }}</h3>
                @if ((int) ($item->seller_id ?? 0) !== (int) auth()->id())
                <form class="rating-form" method="post" action="{{ route('orders.reviews.store') }}">
                  @csrf
                  <input type="hidden" name="type" value="product">
                  <input type="hidden" name="reference" value="{{ $order['reference'] }}">
                  <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                  <div class="field">
                    <label for="product-rating-{{ $item->id }}">Product rating</label>
                    <select id="product-rating-{{ $item->id }}" name="rating" required>
                      @foreach (range(5, 1) as $rating)
                        <option value="{{ $rating }}" @selected((int) old('rating', $productReview?->rating ?? 5) === $rating)>{{ $rating }} / 5</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="field">
                    <label for="product-comment-{{ $item->id }}">Comment (optional)</label>
                    <textarea id="product-comment-{{ $item->id }}" name="comment" maxlength="1000">{{ $productReview?->comment }}</textarea>
                  </div>
                  <button class="btn btn-outline btn-sm" type="submit">{{ $productReview ? 'Update product rating' : 'Submit product rating' }}</button>
                </form>
                @if ($item->seller_id && (int) $item->seller_id !== (int) auth()->id() && !in_array((int) $item->seller_id, $sellerReviewsShown, true))
                  @php($sellerReviewsShown[] = (int) $item->seller_id)
                  @php($sellerReview = $order['seller_reviews']->get($item->seller_id))
                  <form class="rating-form" method="post" action="{{ route('orders.reviews.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="seller">
                    <input type="hidden" name="reference" value="{{ $order['reference'] }}">
                    <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                    <div class="field">
                      <label for="seller-rating-{{ $item->id }}">Seller rating</label>
                      <select id="seller-rating-{{ $item->id }}" name="rating" required>
                        @foreach (range(5, 1) as $rating)
                          <option value="{{ $rating }}" @selected((int) old('rating', $sellerReview?->rating ?? 5) === $rating)>{{ $rating }} / 5</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field">
                      <label for="seller-comment-{{ $item->id }}">Comment (optional)</label>
                      <textarea id="seller-comment-{{ $item->id }}" name="comment" maxlength="1000">{{ $sellerReview?->comment }}</textarea>
                    </div>
                    <button class="btn btn-outline btn-sm" type="submit">{{ $sellerReview ? 'Update seller rating' : 'Submit seller rating' }}</button>
                  </form>
                @elseif (!$item->seller_id)
                  <p class="hint">A seller was not assigned to this product when it was purchased, so seller rating is unavailable.</p>
                @endif
                @else
                  <p class="hint">You cannot rate a product you sold yourself.</p>
                @endif
              </div>
            @endforeach
          </section>
        @endif
      </article>
    @endforeach
  </div>
</div>
@endsection
