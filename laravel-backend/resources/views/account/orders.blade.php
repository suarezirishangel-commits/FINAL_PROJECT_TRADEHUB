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
            <span class="badge badge-{{ $status }}">{{ ucfirst($status) }}</span>
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
            @if ($order['tracking_number'])
              <span>Tracking number: <strong>{{ $order['tracking_number'] }}</strong></span>
            @elseif ($status === 'cancelled')
              <span class="hint">This order was cancelled.</span>
            @else
              <span class="hint">No tracking number yet. It will appear here once the seller hands your order to the courier.</span>
            @endif
          </div>
        </div>
      </article>
    @endforeach
  </div>
</div>
@endsection
