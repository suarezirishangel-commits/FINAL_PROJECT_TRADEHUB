@extends('layouts.admin', ['pageTitle' => 'Orders', 'active' => 'admin.orders'])

@section('content')
<div class="admin-topbar">
  <div><h1>Orders</h1><p>Update order status, assign a delivery rider, and add a tracking number.</p></div>
  <span class="hint">{{ $orders->count() }} orders</span>
</div>
@if ($orders->isEmpty())
  <div class="empty"><h3>No orders yet</h3><p>Orders placed at checkout will show up here.</p></div>
@endif
@foreach ($orders as $order)
  <article class="order-card">
    <div class="order-head">
      <div>
        <strong>{{ $order['reference'] }}</strong><br>
        <span class="hint">{{ $order['created_at']?->format('M j, Y · g:i A') }} · {{ $order['customer'] }} ({{ $order['email'] }})</span>
      </div>
      <div style="display:flex;align-items:center;gap:.8rem">
        <span class="badge badge-{{ str_replace('_', '-', $order['status']) }}">{{ ucfirst(str_replace('_', ' ', $order['status'])) }}</span>
        <strong>{{ \App\Support\TradeHubPresenter::price($order['total']) }}</strong>
      </div>
    </div>
    <ul class="order-items">
      @foreach ($order['items'] as $item)
        <li><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>{{ \App\Support\TradeHubPresenter::price($item->total_amount) }}</span></li>
      @endforeach
    </ul>
    <form class="order-manage" method="post" action="{{ route('admin.orders.update') }}">
      @csrf
      @method('PUT')
      <input type="hidden" name="reference" value="{{ $order['reference'] }}">
      <div class="field">
        <label for="status-{{ $order['reference'] }}">Status</label>
        <select id="status-{{ $order['reference'] }}" name="status">
          @foreach (['pending', 'paid', 'out_for_delivery', 'completed', 'cancelled'] as $status)
            <option value="{{ $status }}" @selected($status === $order['status'])>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label for="rider-{{ $order['reference'] }}">Delivery rider</label>
        <select id="rider-{{ $order['reference'] }}" name="rider_id">
          <option value="">Unassigned</option>
          @foreach ($riders as $rider)
            <option value="{{ $rider->id }}" @selected((string) $rider->id === (string) $order['rider_id'])>{{ $rider->full_name }}</option>
          @endforeach
        </select>
        <small class="hint">Required before setting the order to Out for delivery.</small>
        @if ($riders->isEmpty())
          <small class="hint">Add an active user with the Delivery Rider role before dispatch.</small>
        @endif
      </div>
      <div class="field">
        <label for="tracking-{{ $order['reference'] }}">Tracking number</label>
        <input type="text" id="tracking-{{ $order['reference'] }}" name="tracking_number" value="{{ $order['tracking_number'] }}" maxlength="64" placeholder="e.g. J&T 0012345678">
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </article>
@endforeach
@endsection
