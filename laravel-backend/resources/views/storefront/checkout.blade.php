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
      </div>
      <div class="paypal-option">
        <h3>Or pay online with PayPal</h3>
        @if ($paypalClientId)
          <p class="hint">Confirm the payment with PayPal. Your order will be placed after payment succeeds.</p>
          <div id="paypal-button-container"></div>
          <p id="paypal-status" class="hint" role="status" aria-live="polite"></p>
        @else
          <p class="hint">PayPal is not configured yet. Cash on delivery is available.</p>
        @endif
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
@if ($paypalClientId)
  <script src="https://www.paypal.com/sdk/js?client-id={{ urlencode($paypalClientId) }}&currency={{ urlencode($paypalCurrency) }}&intent=capture"></script>
  <script>
    (() => {
      const form = document.querySelector('form[action="{{ route('checkout.place') }}"]');
      const status = document.getElementById('paypal-status');
      const token = form.querySelector('input[name="_token"]').value;
      const fields = ['full_name', 'phone', 'address'];

      async function postJson(url, payload) {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
          },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) {
          throw new Error(result.message || 'PayPal could not complete this request.');
        }
        return result;
      }

      paypal.Buttons({
        createOrder: async () => {
          if (!form.reportValidity()) {
            throw new Error('Please complete your delivery details first.');
          }
          status.textContent = 'Connecting to PayPal…';
          const payload = Object.fromEntries(fields.map((name) => [name, form.elements[name].value]));
          const order = await postJson('{{ route('checkout.paypal.order') }}', payload);
          return order.id;
        },
        onApprove: async (data) => {
          status.textContent = 'Confirming your payment…';
          const result = await postJson('{{ route('checkout.paypal.capture') }}', { order_id: data.orderID });
          window.location.assign(result.redirect_url);
        },
        onCancel: () => {
          status.textContent = 'PayPal checkout was cancelled. Your order has not been placed.';
        },
        onError: (error) => {
          status.textContent = error.message || 'PayPal is unavailable. Please try again or choose cash on delivery.';
        }
      }).render('#paypal-button-container');
    })();
  </script>
@endif
@endsection
