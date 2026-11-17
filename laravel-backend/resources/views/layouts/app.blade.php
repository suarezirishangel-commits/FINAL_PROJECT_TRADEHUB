<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle ?? 'TradeHub' }} · {{ $siteContent->setting('site_name', 'TradeHub') }}</title>
  <meta name="description" content="{{ $siteContent->setting('tagline') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
  @if (request()->routeIs('orders', 'delivery.orders'))
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  @endif
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="{{ route('home') }}">
      <span class="brand-mark">TH</span>
      <span class="brand-name">{{ $siteContent->setting('site_name', 'TradeHub') }}<small>Local marketplace</small></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>
    <nav class="primary-nav" id="primary-nav" aria-label="Main navigation">
      <a href="{{ route('home') }}" class="{{ ($active ?? '') === 'home' ? 'is-active' : '' }}">Home</a>
      <a href="{{ route('products') }}" class="{{ ($active ?? '') === 'products' ? 'is-active' : '' }}">Products</a>
      <a href="{{ route('trades.index') }}" class="{{ ($active ?? '') === 'trades' ? 'is-active' : '' }}">Trades</a>
      @auth
        <a href="{{ route('orders') }}" class="{{ ($active ?? '') === 'orders' ? 'is-active' : '' }}">My orders</a>
        @if (auth()->user()->isAdministrator())
          <a href="{{ route('admin.dashboard') }}" class="nav-admin">Admin panel</a>
        @elseif (auth()->user()->isDeliveryRider())
          <a href="{{ route('delivery.orders') }}" class="{{ ($active ?? '') === 'delivery' ? 'is-active' : '' }}">Delivery dashboard</a>
        @endif
      @endauth
      <div class="nav-actions">
        <a class="cart-link" href="{{ route('cart') }}" aria-label="Cart, {{ $cartCount }} items">
          Cart<span class="cart-count" id="cart-count">{{ $cartCount }}</span>
        </a>
        @auth
          <a class="btn btn-ghost" href="{{ route('profile') }}">{{ explode(' ', auth()->user()->full_name)[0] }}</a>
          <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline" type="submit">Log out</button>
          </form>
        @else
          <a class="btn btn-ghost" href="{{ route('login') }}">Log in</a>
          <a class="btn btn-primary" href="{{ route('register') }}">Sign up</a>
        @endauth
      </div>
    </nav>
  </div>
</header>
<main id="main">
  @include('partials.flash')
  @yield('content')
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-light" href="{{ route('home') }}">
        <span class="brand-mark">TH</span>
        <span class="brand-name">{{ $siteContent->setting('site_name', 'TradeHub') }}</span>
      </a>
      <p class="footer-copy">{{ $siteContent->setting('tagline') }}</p>
    </div>
    <div>
      <h4>Marketplace</h4>
      <ul>
        <li><a href="{{ route('products') }}">Browse products</a></li>
        <li><a href="{{ route('products', ['available' => 1]) }}">In stock today</a></li>
        <li><a href="{{ route('cart') }}">Your cart</a></li>
      </ul>
    </div>
    <div>
      <h4>Account</h4>
      <ul>
        @auth
          <li><a href="{{ route('profile') }}">Profile</a></li>
          <li><a href="{{ route('orders') }}">Order history</a></li>
        @else
          <li><a href="{{ route('login') }}">Log in</a></li>
          <li><a href="{{ route('register') }}">Create account</a></li>
        @endauth
      </ul>
    </div>
    <div>
      <h4>Contact</h4>
      <ul>
        <li>{{ $siteContent->setting('contact_email') }}</li>
        <li>{{ $siteContent->setting('contact_phone') }}</li>
        <li>Mon–Sat, 8:00 AM – 6:00 PM</li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; {{ date('Y') }} {{ $siteContent->setting('site_name', 'TradeHub') }}. Supporting local sellers.</span>
    <span>Commission-based marketplace</span>
  </div>
</footer>
@if (request()->routeIs('orders', 'delivery.orders'))
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
  <script src="{{ asset('assets/js/delivery-map.js') }}" defer></script>
@endif
<script src="{{ asset('assets/js/main.js') }}" defer></script>
</body>
</html>
