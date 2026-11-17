@extends('layouts.admin', ['pageTitle' => 'Products', 'active' => 'admin.products'])

@section('content')
<div class="admin-topbar">
  <div><h1>Products</h1><p>{{ $products->count() }} listings in the catalog.</p></div>
  <a class="btn btn-primary" href="{{ route('admin.products', ['action' => 'new']) }}">Add product</a>
</div>
@if ($showForm)
  <section class="card" style="margin-bottom:1.25rem">
    <div class="card-head">
      <h2>{{ $editing ? 'Edit product' : 'New product' }}</h2>
      <a class="btn btn-ghost btn-sm" href="{{ route('admin.products') }}">Cancel</a>
    </div>
    <form method="post" action="{{ route('admin.products.save') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="id" value="{{ old('id', $editing?->id ?? 0) }}">
      <div class="form-grid">
        <div class="field span-2">
          <label for="name">Product name</label>
          <input type="text" id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="150">
        </div>
        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">Choose category</option>
            @foreach ($categories as $category)
              <option value="{{ $category->id }}" @selected((string) old('category_id', $editing?->category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="seller_id">Seller</label>
          <select id="seller_id" name="seller_id" required>
            <option value="">Choose seller</option>
            @foreach ($sellers as $seller)
              <option value="{{ $seller->id }}" @selected((string) old('seller_id', $editing?->seller_id) === (string) $seller->id)>{{ $seller->full_name }}</option>
            @endforeach
          </select>
          @if ($sellers->isEmpty())
            <small class="hint">Promote an active user to Seller in Admin → Users first.</small>
          @endif
        </div>
        <div class="field">
          <label for="price">Price (₱)</label>
          <input type="number" id="price" name="price" step="0.01" min="0" value="{{ old('price', $editing?->price) }}" required>
        </div>
        <div class="field">
          <label for="stock">Stock quantity</label>
          <input type="number" id="stock" name="stock" min="0" value="{{ old('stock', $editing?->stock ?? 0) }}" required>
        </div>
        <div class="field">
          <label for="is_active">Visibility</label>
          <label class="checkline" style="margin-top:.5rem">
            <input type="checkbox" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $editing?->is_active ?? true))>
            Show in storefront
          </label>
        </div>
        <div class="field span-2">
          <label for="image">Product photo</label>
          @php($previewImage = \App\Support\TradeHubPresenter::productImageUrl($editing?->image))
          <div class="image-field">
            <div class="image-preview">
              @if ($previewImage)
                <img src="{{ $previewImage }}" alt="Current product photo">
              @else
                <span aria-hidden="true">{{ \App\Support\TradeHubPresenter::productIcon($editing?->category?->name) }}</span>
              @endif
            </div>
            <div class="image-field-body">
              <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
              <p class="hint">A clear photo that identifies the product. JPG, PNG, WebP, or GIF, up to 2MB.</p>
              @if ($previewImage)
                <label class="checkline"><input type="checkbox" name="remove_image" value="1">Remove current photo</label>
              @endif
            </div>
          </div>
        </div>
        <div class="field span-2">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="2000">{{ old('description', $editing?->description) }}</textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create product' }}</button>
    </form>
  </section>
@endif
<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Product</th><th>Category</th><th>Seller</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse ($products as $product)
      <tr>
        <td>
          <div class="admin-product-cell">
            <span class="admin-thumb">
              @php($thumb = \App\Support\TradeHubPresenter::productImageUrl($product->image))
              @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy">
              @else
                <span aria-hidden="true">{{ \App\Support\TradeHubPresenter::productIcon($product->category->name) }}</span>
              @endif
            </span>
            <strong>{{ $product->name }}</strong>
          </div>
        </td>
        <td>{{ $product->category->name }}</td>
        <td>{{ $product->seller?->full_name ?? 'Unassigned' }}</td>
        <td class="num">{{ \App\Support\TradeHubPresenter::price($product->price) }}</td>
        <td class="num">{{ $product->stock }}</td>
        <td>
          @if (!$product->is_active)
            <span class="badge badge-out">Hidden</span>
          @elseif ($product->stock === 0)
            <span class="badge badge-out">Out of stock</span>
          @else
            <span class="badge badge-in">Live</span>
          @endif
        </td>
        <td>
          <div class="row-actions">
            <a class="btn btn-outline btn-sm" href="{{ route('admin.products', ['id' => $product->id]) }}">Edit</a>
            <form class="inline-form" method="post" action="{{ route('admin.products.action') }}">
              @csrf
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="{{ $product->id }}">
              <button class="btn btn-ghost btn-sm" type="submit">{{ $product->is_active ? 'Hide' : 'Show' }}</button>
            </form>
            <form class="inline-form" method="post" action="{{ route('admin.products.action') }}" onsubmit="return confirm('Delete this product?');">
              @csrf
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="{{ $product->id }}">
              <button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="hint">No products yet. Add your first listing.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
