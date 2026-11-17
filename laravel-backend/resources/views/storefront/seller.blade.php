@extends('layouts.app', ['pageTitle' => $seller->full_name, 'active' => 'products'])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Seller profile</div>
    <h1>{{ $seller->full_name }}</h1>
    <p>
      @if ($ratingAverage !== null)
        {{ number_format((float) $ratingAverage, 1) }} / 5 from {{ $ratingCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingCount) }}
      @else
        No seller ratings yet
      @endif
    </p>
  </div>
  <section class="section">
    <div class="section-head"><div><div class="kicker">Seller catalog</div><h2>Products</h2></div></div>
    @if ($products->isEmpty())
      <p class="hint">This seller has no active products right now.</p>
    @else
      <div class="product-grid">
        @foreach ($products as $product)
          @include('partials.product-card', ['product' => $product])
        @endforeach
      </div>
    @endif
  </section>
  <section class="section review-section">
    <div class="section-head"><div><div class="kicker">Customer feedback</div><h2>Seller ratings</h2></div></div>
    @forelse ($reviews as $review)
      <article class="review-entry">
        <div class="review-heading">
          <strong>{{ $review->reviewer->full_name }}</strong>
          <span>{{ $review->rating }} / 5 · {{ $review->created_at?->format('M j, Y') }}</span>
        </div>
        @if ($review->comment)
          <p>{{ $review->comment }}</p>
        @endif
      </article>
    @empty
      <p class="hint">No customer ratings yet.</p>
    @endforelse
    {{ $reviews->links() }}
  </section>
</div>
@endsection
