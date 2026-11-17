@extends('layouts.app', ['pageTitle' => $product->name, 'active' => 'products'])

@section('content')
<div class="container">
  <p class="hint" style="padding-top:1.5rem;font-size:.85rem">
    <a href="{{ route('home') }}">Home</a> /
    <a href="{{ route('products') }}">Products</a> /
    <a href="{{ route('products', ['category' => $product->category_id]) }}">{{ $product->category->name }}</a>
  </p>
  <div class="detail">
    <div class="detail-media tone-{{ \App\Support\TradeHubPresenter::productTone((int) $product->id) }}">
      @php($imageUrl = \App\Support\TradeHubPresenter::productImageUrl($product->image))
      @if ($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy">
      @else
        <span class="thumb-icon" aria-hidden="true">{{ \App\Support\TradeHubPresenter::productIcon($product->category->name) }}</span>
      @endif
    </div>
    <div>
      <span class="product-cat">{{ $product->category->name }}</span>
      <h1>{{ $product->name }}</h1>
      <p class="price">{{ \App\Support\TradeHubPresenter::price($product->price) }}</p>
      <p>{!! nl2br(e($product->description)) !!}</p>
      <ul class="specs">
        <li><span>Availability</span><span>
          @if ($product->stock <= 0)
            <span class="badge badge-out">Out of stock</span>
          @elseif ($product->stock <= 10)
            <span class="badge badge-low">{{ $product->stock }} left in stock</span>
          @else
            <span class="badge badge-in">{{ $product->stock }} in stock</span>
          @endif
        </span></li>
        <li><span>Category</span><span>{{ $product->category->name }}</span></li>
        <li><span>Listed</span><span>{{ $product->created_at?->format('M j, Y') }}</span></li>
        <li>
          <span>Product rating</span>
          <span>
            @if ($productRatingAverage !== null)
              {{ number_format((float) $productRatingAverage, 1) }} / 5 ({{ $productRatingCount }} {{ \Illuminate\Support\Str::plural('review', $productRatingCount) }})
            @else
              No ratings yet
            @endif
          </span>
        </li>
        @if ($product->seller)
          <li>
            <span>Seller</span>
            <span>
              <a href="{{ route('sellers.show', $product->seller->id) }}">{{ $product->seller->full_name }}</a>
              ·
              @if ($sellerRatingAverage !== null)
                {{ number_format((float) $sellerRatingAverage, 1) }} / 5 ({{ $sellerRatingCount }} {{ \Illuminate\Support\Str::plural('rating', $sellerRatingCount) }})
              @else
                No seller ratings yet
              @endif
            </span>
          </li>
        @endif
      </ul>
      @if ($product->stock > 0)
        <form class="buy-form" method="post" action="{{ route('cart.update') }}">
          @csrf
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="{{ $product->id }}">
          <div class="field">
            <label for="qty">Quantity</label>
            <input type="number" id="qty" name="quantity" value="1" min="1" max="{{ $product->stock }}" data-max="{{ $product->stock }}" required>
          </div>
          <button class="btn btn-primary" type="submit">Add to cart</button>
          <a class="btn btn-outline" href="{{ route('cart') }}">Go to cart</a>
        </form>
      @else
        <p class="badge badge-out" style="padding:.5rem .8rem">This product is currently unavailable. Check back soon.</p>
      @endif
    </div>
  </div>
  <section class="section review-section">
    <div class="section-head">
      <div>
        <div class="kicker">Customer feedback</div>
        <h2>Product ratings</h2>
      </div>
      <strong>{{ $productRatingAverage !== null ? number_format((float) $productRatingAverage, 1).' / 5' : 'Not rated yet' }}</strong>
    </div>
    @forelse ($productReviews as $review)
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
    {{ $productReviews->links() }}
  </section>
  @if ($related->isNotEmpty())
    <section class="section" style="padding-top:0">
      <div class="section-head"><div><div class="kicker">More in this category</div><h2>You may also like</h2></div></div>
      <div class="product-grid">
        @foreach ($related as $relatedProduct)
          @include('partials.product-card', ['product' => $relatedProduct])
        @endforeach
      </div>
    </section>
  @endif
</div>
@endsection
