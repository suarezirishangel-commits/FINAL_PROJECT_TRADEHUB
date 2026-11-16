@extends('layouts.admin', ['pageTitle' => 'Categories', 'active' => 'admin.categories'])

@section('content')
<div class="admin-topbar">
  <div><h1>Categories</h1><p>Group listings so buyers can filter the catalog.</p></div>
  <a class="btn btn-primary" href="{{ route('admin.categories', ['action' => 'new']) }}">Add category</a>
</div>
@if ($showForm)
  <section class="card" style="margin-bottom:1.25rem">
    <div class="card-head">
      <h2>{{ $editing ? 'Edit category' : 'New category' }}</h2>
      <a class="btn btn-ghost btn-sm" href="{{ route('admin.categories') }}">Cancel</a>
    </div>
    <form method="post" action="{{ route('admin.categories.save') }}">
      @csrf
      <input type="hidden" name="id" value="{{ old('id', $editing?->id ?? 0) }}">
      <div class="form-grid">
        <div class="field">
          <label for="name">Category name</label>
          <input type="text" id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="100">
        </div>
        <div class="field">
          <label for="slug">URL slug <span class="hint">(optional)</span></label>
          <input type="text" id="slug" name="slug" value="{{ old('slug', $editing?->slug) }}" maxlength="120">
        </div>
        <div class="field span-2">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="500">{{ old('description', $editing?->description) }}</textarea>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create category' }}</button>
    </form>
  </section>
@endif
<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Category</th><th>Slug</th><th class="num">Products</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse ($categories as $category)
      <tr>
        <td><strong>{{ $category->name }}</strong><br><small class="hint">{{ $category->description }}</small></td>
        <td><code>{{ $category->slug }}</code></td>
        <td class="num">{{ $category->products_count }}</td>
        <td>
          <div class="row-actions">
            <a class="btn btn-outline btn-sm" href="{{ route('admin.categories', ['id' => $category->id]) }}">Edit</a>
            <form class="inline-form" method="post" action="{{ route('admin.categories.delete') }}" onsubmit="return confirm('Delete this category?');">
              @csrf
              <input type="hidden" name="id" value="{{ $category->id }}">
              <button class="btn btn-danger btn-sm" type="submit" @disabled($category->products_count > 0) @if($category->products_count > 0) title="Category still has products" @endif>Delete</button>
            </form>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="hint">No categories yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
