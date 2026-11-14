@extends('layouts.app', ['pageTitle' => $siteContent->setting('hero_title', 'Local marketplace'), 'active' => 'home'])

@section('content')
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow">Local products · Fair prices</span>
      <h1>{{ $siteContent->setting('hero_title', 'Discover the best of your local market') }}</h1>
      <p class="lead">{{ $siteContent->setting('hero_text') }}</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="{{ route('products') }}">Browse products</a>
        @guest
          <a class="btn btn-outline" href="{{ route('register') }}">Create a free account</a>
        @else
          <a class="btn btn-outline" href="{{ route('orders') }}">View my orders</a>
        @endguest
      </div>
      <div class="hero-stats">
        <div><strong>{{ $stats['products'] }}</strong><span>Products listed</span></div>
        <div><strong>{{ $stats['categories'] }}</strong><span>Local categories</span></div>
        <div><strong>{{ $stats['orders'] }}</strong><span>Orders placed</span></div>
      </div>
    </div>
  </div>
</section>

@if ($announcements->isNotEmpty())
  <section class="section">
    <div class="container">
      <div class="section-head">
        <div><div class="kicker">Updates</div><h2>Announcements</h2></div>
      </div>
      <div class="highlights">
        @foreach ($announcements as $announcement)
          <article class="highlight">
            <h3>{{ $announcement['title'] ?? $announcement->title }}</h3>
            <p>{!! nl2br(e($announcement['body'] ?? $announcement->body)) !!}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endif

<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <div class="kicker">What we offer</div>
        <h2>A marketplace built around discovery</h2>
        <p>Everything a buyer needs to find local goods quickly — and everything a seller needs to keep listings accurate.</p>
      </div>
    </div>
    <div class="highlights">
      <article class="highlight"><div class="icon" aria-hidden="true">🛍️</div><h3>Product catalog</h3><p>Browse verified local goods with clear pricing, category labels, and real-time stock availability.</p></article>
      <article class="highlight"><div class="icon" aria-hidden="true">🔎</div><h3>Advanced search</h3><p>Search across product names and descriptions, with sorting to surface the best match first.</p></article>
      <article class="highlight"><div class="icon" aria-hidden="true">🎚️</div><h3>Category &amp; price filters</h3><p>Narrow results by category, price range, and availability so you only see what you can actually order.</p></article>
      <article class="highlight"><div class="icon" aria-hidden="true">✉️</div><h3>Order confirmations</h3><p>Every checkout can send an email receipt with your items, total, and delivery details.</p></article>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head">
      <div><div class="kicker">Fresh listings</div><h2>Popular with local buyers</h2></div>
      <a class="btn btn-outline" href="{{ route('products') }}">See all products</a>
    </div>
    <div class="product-grid">
      @foreach ($featured as $product)
        @include('partials.product-card', ['product' => $product])
      @endforeach
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="highlight" style="background:#0f2f2b;color:#dfeeea;border-color:#0f2f2b;padding:2rem">
      <div class="kicker" style="color:#f59e0b">Our business</div>
      <h2 style="color:#fff">Supporting micro-entrepreneurs, one order at a time</h2>
      <p style="max-width:70ch;color:#bcd6d0">{{ $siteContent->setting('about_text') }}</p>
    </div>
  </div>
</section>
@endsection
