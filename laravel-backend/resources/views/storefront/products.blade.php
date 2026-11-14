@extends('layouts.app', ['pageTitle' => 'Products', 'active' => 'products'])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Product catalog</div>
    <h1>Browse local products</h1>
    <p>Search by name, filter by category and price, and only order what is in stock.</p>
  </div>
  <div class="catalog">
    <aside class="filters">
      <h2>Filter products</h2>
      <form method="get" action="{{ route('products') }}">
        <div class="field">
          <label for="q">Search</label>
          <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="e.g. mangoes, coffee">
        </div>
        <div class="field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">All categories</option>
            @foreach ($categories as $category)
              <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label>Price range (₱)</label>
          <div class="field-row">
            <input type="number" name="min_price" min="0" step="0.01" placeholder="Min" value="{{ $filters['min_price'] }}">
            <input type="number" name="max_price" min="0" step="0.01" placeholder="Max" value="{{ $filters['max_price'] }}">
          </div>
        </div>
        <label class="checkline">
          <input type="checkbox" name="available" value="1" @checked($filters['available'] === '1')>
          In stock only
        </label>
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <div class="filter-actions">
          <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
          <a class="btn btn-ghost btn-block" href="{{ route('products') }}">Reset</a>
        </div>
      </form>
    </aside>
    <div>
      <div class="catalog-toolbar">
        <form class="search-bar" method="get" action="{{ route('products') }}">
          <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search products…" aria-label="Search products">
          @foreach (['category', 'min_price', 'max_price', 'available'] as $key)
            @if ($filters[$key] !== '')
              <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
            @endif
          @endforeach
          <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
          <button class="btn btn-primary" type="submit">Search</button>
        </form>
        <form method="get" action="{{ route('products') }}">
          @foreach (['q', 'category', 'min_price', 'max_price', 'available'] as $key)
            @if ($filters[$key] !== '')
              <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
            @endif
          @endforeach
          <label class="hint" for="sort" style="margin:0 .3rem 0 0">Sort</label>
          <select id="sort" name="sort" onchange="this.form.submit()">
            <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price: low to high</option>
            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price: high to low</option>
            <option value="name" @selected($filters['sort'] === 'name')>Name A–Z</option>
          </select>
        </form>
      </div>
      <div class="active-filters">
        @if ($filters['q'] !== '')
          <a class="chip" href="{{ route('products', array_merge(request()->query(), ['q' => null, 'page' => 1])) }}">“{{ $filters['q'] }}” ✕</a>
        @endif
        @if ($activeCategory)
          <a class="chip" href="{{ route('products', array_merge(request()->query(), ['category' => null, 'page' => 1])) }}">{{ $activeCategory->name }} ✕</a>
        @endif
        @if ($filters['min_price'] !== '' || $filters['max_price'] !== '')
          <a class="chip" href="{{ route('products', array_merge(request()->query(), ['min_price' => null, 'max_price' => null, 'page' => 1])) }}">
            ₱{{ $filters['min_price'] !== '' ? number_format((float) $filters['min_price'], 0) : '0' }} – ₱{{ $filters['max_price'] !== '' ? number_format((float) $filters['max_price'], 0) : 'any' }} ✕
          </a>
        @endif
        @if ($filters['available'] === '1')
          <a class="chip" href="{{ route('products', array_merge(request()->query(), ['available' => null, 'page' => 1])) }}">In stock ✕</a>
        @endif
      </div>
      <p class="count" style="color:var(--muted);font-size:.9rem;margin:0 0 1rem">
        {{ $products->total() }} product{{ $products->total() === 1 ? '' : 's' }} found
      </p>
      @if ($products->isNotEmpty())
        <div class="product-grid">
          @foreach ($products as $product)
            @include('partials.product-card', ['product' => $product])
          @endforeach
        </div>
        @if ($products->hasPages())
          <nav class="pagination" aria-label="Product pages">
            @if ($products->onFirstPage())
              <span class="is-current" aria-disabled="true">Prev</span>
            @else
              <a href="{{ $products->previousPageUrl() }}">Prev</a>
            @endif
            @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
              @if ($page === $products->currentPage())
                <span class="is-current" aria-current="page">{{ $page }}</span>
              @else
                <a href="{{ $url }}">{{ $page }}</a>
              @endif
            @endforeach
            @if ($products->hasMorePages())
              <a href="{{ $products->nextPageUrl() }}">Next</a>
            @endif
          </nav>
        @endif
      @else
        <div class="empty">
          <h3>No products match your filters</h3>
          <p>Try a broader search term, a different category, or a wider price range.</p>
          <a class="btn btn-primary" href="{{ route('products') }}">Clear all filters</a>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
