@extends('layouts.app', ['pageTitle' => 'Checkout', 'active' => ''])

@section('content')
<div class="page-head"><div class="container">
  <div class="kicker">Checkout step 2 of 2</div>
  <h1>Confirm your order</h1>
  <p>Delivery details and payment method.</p>
</div></div>
<div class="container" style="padding-bottom:3rem">
  <div class="cart-layout">
    <form class="panel" method="post" action="{{ route('checkout.place') }}">
      @csrf
      <h2>Delivery details</h2>
      <div class="field">
        <label for="full_name">Recipient name</label>
        <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" required maxlength="120">
      </div>
      <div class="field">
        <label for="phone">Contact number</label>
        <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required maxlength="30" placeholder="09XX XXX XXXX">
      </div>
      <div class="field">
        <label for="address">Delivery address</label>
        <textarea id="address" name="address" required maxlength="255" placeholder="House number, street, barangay, city">{{ old('address', $user->address) }}</textarea>
      </div>
      <h2 style="margin-top:1.5rem">Payment method</h2>
      <div class="payment-options">
        <label>
          <input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')>
          <span><strong>Cash on delivery</strong><br><small class="hint">Pay the rider when your order arrives.</small></span>
        </label>
        <label>
          <input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')>
          <span><strong>GCash</strong><br><small class="hint">Send payment to the seller's GCash number after checkout.</small></span>
        </label>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Place order</button>
      <a class="btn btn-ghost btn-block" href="{{ route('cart') }}" style="margin-top:.5rem">Back to cart</a>
    </form>
    <aside class="panel">
      <h2>Order summary</h2>
      @foreach ($items as $item)
        <div class="summary-row">
          <span>{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
          <span>{{ \App\Support\TradeHubPresenter::price($item['line_total']) }}</span>
        </div>
      @endforeach
      <div class="summary-row total"><span>Total</span><span>{{ \App\Support\TradeHubPresenter::price($total) }}</span></div>
      <p class="hint">Prices include the platform commission. Delivery is arranged with the seller.</p>
    </aside>
  </div>
</div>
@endsection
