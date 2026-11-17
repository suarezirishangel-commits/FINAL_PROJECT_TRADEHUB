@extends('layouts.app', ['pageTitle' => 'Delivery dashboard', 'active' => 'delivery'])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Delivery rider</div>
    <h1>Assigned deliveries</h1>
    <p>View your assigned orders and update their delivery progress.</p>
  </div>
  <div style="padding-bottom:3rem">
    @if ($orders->isEmpty())
      <div class="empty">
        <h3>No assigned deliveries</h3>
        <p>Orders assigned to your rider account will appear here.</p>
      </div>
    @endif
    @foreach ($orders as $order)
      <article class="order-card rider-order">
        <div class="order-head">
          <div>
            <strong>{{ $order['reference'] }}</strong><br>
            <span class="hint">{{ $order['created_at']?->format('M j, Y · g:i A') }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:.8rem">
            <span class="badge badge-{{ str_replace('_', '-', $order['status']) }}">{{ ucfirst(str_replace('_', ' ', $order['status'])) }}</span>
            <strong>{{ \App\Support\TradeHubPresenter::price($order['total']) }}</strong>
          </div>
        </div>
        <div class="rider-order-details">
          <p><strong>Customer:</strong> {{ $order['customer'] }}{{ $order['phone'] ? ' · '.$order['phone'] : '' }}</p>
          <p><strong>Deliver to:</strong> {{ $order['delivery_address'] ?: 'No delivery address provided' }}</p>
          <ul class="order-items">
            @foreach ($order['items'] as $item)
              <li>
                <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                <span>{{ \App\Support\TradeHubPresenter::price($item->total_amount) }}</span>
              </li>
            @endforeach
          </ul>
          @if ($order['tracking_number'])
            <p><strong>Tracking number:</strong> {{ $order['tracking_number'] }}</p>
          @endif
          <section class="live-tracking"
                   data-delivery-map
                   data-mode="rider"
                   data-reference="{{ $order['reference'] }}"
                   data-endpoint="{{ route('delivery.location') }}"
                   data-csrf-token="{{ csrf_token() }}"
                   data-gps-active="{{ $order['status'] === 'out_for_delivery' ? '1' : '0' }}"
                   data-latitude="{{ $order['latitude'] ?? '' }}"
                   data-longitude="{{ $order['longitude'] ?? '' }}"
                   data-location-updated-at="{{ $order['location_updated_at']?->toIso8601String() }}">
            <h3>Delivery map</h3>
            <p class="map-status" data-map-status aria-live="polite">Waiting for GPS location.</p>
            <div class="delivery-map" data-map-canvas></div>
            <div class="rider-gps-controls">
              <button class="btn btn-outline" type="button" data-gps-toggle @disabled($order['status'] !== 'out_for_delivery')>
                {{ $order['status'] === 'out_for_delivery' ? 'Start live GPS' : 'GPS available after delivery starts' }}
              </button>
              <p class="hint" data-gps-status aria-live="polite">
                {{ $order['status'] === 'out_for_delivery' ? 'Choose Start live GPS to share your location with this order’s customer.' : 'Start the delivery before sharing GPS.' }}
              </p>
            </div>
          </section>
        </div>
        <div class="rider-actions">
          @if (in_array($order['status'], ['pending', 'paid'], true))
            <form method="post" action="{{ route('delivery.orders.update') }}">
              @csrf
              <input type="hidden" name="reference" value="{{ $order['reference'] }}">
              <input type="hidden" name="action" value="start">
              <button class="btn btn-primary" type="submit">Start delivery</button>
            </form>
          @elseif ($order['status'] === 'out_for_delivery' && $order['payment_method'] === 'cod' && $order['paid_at'] === null)
            <form method="post" action="{{ route('delivery.orders.update') }}">
              @csrf
              <input type="hidden" name="reference" value="{{ $order['reference'] }}">
              <input type="hidden" name="action" value="collect_payment">
              <button class="btn btn-outline" type="submit">Mark COD payment collected</button>
            </form>
          @elseif ($order['status'] === 'out_for_delivery')
            <form method="post" action="{{ route('delivery.orders.update') }}">
              @csrf
              <input type="hidden" name="reference" value="{{ $order['reference'] }}">
              <input type="hidden" name="action" value="complete">
              <button class="btn btn-primary" type="submit">Mark delivered</button>
            </form>
          @elseif ($order['status'] === 'completed')
            <span class="hint">Delivery completed.</span>
          @elseif ($order['status'] === 'cancelled')
            <span class="hint">This order was cancelled.</span>
          @endif
        </div>
      </article>
    @endforeach
  </div>
</div>
@endsection
