@extends('layouts.app', ['pageTitle' => 'Your cart', 'active' => ''])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Checkout step 1 of 2</div>
    <h1>Your cart</h1>
    <p>{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }} ready for checkout.</p>
  </div>
  @if ($items->isEmpty())
    <div class="empty" style="margin-bottom:3rem">
      <h3>Your cart is empty</h3>
      <p>Browse the catalog and add a few local favorites.</p>
      <a class="btn btn-primary" href="{{ route('products') }}">Browse products</a>
    </div>
  @else
    <div class="cart-layout">
      <div class="panel">
        <h2>Items</h2>
        @foreach ($items as $item)
          <div class="cart-row">
            <div>
              <h3><a href="{{ route('product', $item['product']->id) }}">{{ $item['product']->name }}</a></h3>
              <small>{{ $item['product']->category->name }} · {{ \App\Support\TradeHubPresenter::price($item['product']->price) }} each</small>
            </div>
            <form class="qty-form" method="post" action="{{ route('cart.update') }}">
              @csrf
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="product_id" value="{{ $item['product']->id }}">
              <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['product']->stock }}" data-max="{{ $item['product']->stock }}" aria-label="Quantity for {{ $item['product']->name }}">
              <button class="btn btn-outline btn-sm" type="submit">Update</button>
            </form>
            <form class="cart-remove" method="post" action="{{ route('cart.update') }}">
              @csrf
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="{{ $item['product']->id }}">
              <button class="btn btn-danger btn-sm" type="submit" aria-label="Remove {{ $item['product']->name }}">Remove</button>
            </form>
          </div>
        @endforeach
      </div>
      <aside class="panel">
        <h2>Order summary</h2>
        @foreach ($items as $item)
          <div class="summary-row">
            <span>{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
            <span>{{ \App\Support\TradeHubPresenter::price($item['line_total']) }}</span>
          </div>
        @endforeach
        <div class="summary-row total">
          <span>Subtotal</span>
          <span>{{ \App\Support\TradeHubPresenter::price($total) }}</span>
        </div>
        <p class="hint" style="margin:.6rem 0 1rem">Delivery fees are arranged with the seller after checkout.</p>
        <a class="btn btn-primary btn-block" href="{{ route('checkout') }}">Proceed to checkout</a>
        <a class="btn btn-ghost btn-block" href="{{ route('products') }}" style="margin-top:.5rem">Continue shopping</a>
      </aside>
    </div>
  @endif
</div>
@endsection
