@php
  $stock = (int) $product->stock;
  $imageUrl = \App\Support\TradeHubPresenter::productImageUrl($product->image);
@endphp
<article class="product-card">
  <div class="product-thumb tone-{{ \App\Support\TradeHubPresenter::productTone((int) $product->id) }}">
    @if ($imageUrl)
      <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy">
    @else
      <span class="thumb-icon" aria-hidden="true">{{ \App\Support\TradeHubPresenter::productIcon($product->category->name) }}</span>
    @endif
  </div>
  <div class="product-body">
    <span class="product-cat">{{ $product->category->name }}</span>
    <h3><a href="{{ route('product', $product->id) }}">{{ $product->name }}</a></h3>
    <div class="product-meta">
      <span class="price">{{ \App\Support\TradeHubPresenter::price($product->price) }}</span>
      @if ($stock <= 0)
        <span class="badge badge-out">Out of stock</span>
      @elseif ($stock <= 10)
        <span class="badge badge-low">Only {{ $stock }} left</span>
      @else
        <span class="badge badge-in">In stock</span>
      @endif
    </div>
    <a class="btn btn-outline btn-sm btn-block" href="{{ route('product', $product->id) }}">View product</a>
  </div>
</article>
