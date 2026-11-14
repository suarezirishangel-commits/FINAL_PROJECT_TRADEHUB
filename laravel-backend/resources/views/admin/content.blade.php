@extends('layouts.admin', ['pageTitle' => 'Content & announcements', 'active' => 'admin.content'])

@section('content')
<div class="admin-topbar">
  <div><h1>Content &amp; announcements</h1><p>Edit storefront copy and publish marketplace notices.</p></div>
</div>
<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Website content</h2></div>
    <form method="post" action="{{ route('admin.content.settings') }}">
      @csrf
      @method('PUT')
      <div class="form-grid">
        <div class="field"><label for="site_name">Site name</label><input type="text" id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? '') }}" required maxlength="60"></div>
        <div class="field"><label for="tagline">Tagline</label><input type="text" id="tagline" name="tagline" value="{{ old('tagline', $settings['tagline'] ?? '') }}" maxlength="140"></div>
        <div class="field span-2"><label for="hero_title">Home headline</label><input type="text" id="hero_title" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}" maxlength="140"></div>
        <div class="field span-2"><label for="hero_text">Home introduction</label><textarea id="hero_text" name="hero_text" maxlength="500">{{ old('hero_text', $settings['hero_text'] ?? '') }}</textarea></div>
        <div class="field span-2"><label for="about_text">Business overview</label><textarea id="about_text" name="about_text" maxlength="800">{{ old('about_text', $settings['about_text'] ?? '') }}</textarea></div>
        <div class="field"><label for="contact_email">Contact email</label><input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $settings['contact_email'] ?? '') }}" required></div>
        <div class="field"><label for="contact_phone">Contact phone</label><input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}" maxlength="30"></div>
        <div class="field">
          <label for="commission_rate">Commission rate (%)</label>
          <input type="number" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100" value="{{ old('commission_rate', $settings['commission_rate'] ?? '0') }}" required>
          <p class="hint">Used for the commission totals in sales reports.</p>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save content</button>
    </form>
  </section>
  <div>
    <section class="card" style="margin-bottom:1.25rem">
      <div class="card-head">
        <h2>{{ $editingAnnouncement ? 'Edit announcement' : 'New announcement' }}</h2>
        @if ($editingAnnouncement)<a class="btn btn-ghost btn-sm" href="{{ route('admin.content') }}">Cancel</a>@endif
      </div>
      <form method="post" action="{{ route('admin.content.announcements.save') }}">
        @csrf
        <input type="hidden" name="id" value="{{ old('id', $editingAnnouncement?->id ?? 0) }}">
        <div class="field"><label for="title">Title</label><input type="text" id="title" name="title" value="{{ old('title', $editingAnnouncement?->title) }}" required maxlength="150"></div>
        <div class="field"><label for="body">Message</label><textarea id="body" name="body" maxlength="1000" required>{{ old('body', $editingAnnouncement?->body) }}</textarea></div>
        <label class="checkline"><input type="checkbox" name="is_published" value="1" @checked((bool) old('is_published', $editingAnnouncement?->is_published ?? true))>Publish on the storefront</label>
        <button class="btn btn-primary" type="submit">{{ $editingAnnouncement ? 'Save announcement' : 'Publish announcement' }}</button>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><h2>All announcements</h2></div>
      @if ($announcements->isEmpty())
        <p class="hint">No announcements yet.</p>
      @else
        <ul style="list-style:none;padding:0;margin:0">
          @foreach ($announcements as $announcement)
            <li style="padding:.7rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
              <strong>{{ $announcement->title }}</strong><br>
              <small class="hint">{{ $announcement->created_at?->format('M j, Y') }} ·
                <span class="badge {{ $announcement->is_published ? 'badge-in' : 'badge-out' }}">{{ $announcement->is_published ? 'Published' : 'Draft' }}</span>
              </small>
              <div class="row-actions" style="margin-top:.5rem">
                <a class="btn btn-outline btn-sm" href="{{ route('admin.content', ['announcement' => $announcement->id]) }}">Edit</a>
                <form class="inline-form" method="post" action="{{ route('admin.content.announcements.action') }}">
                  @csrf
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="{{ $announcement->id }}">
                  <button class="btn btn-ghost btn-sm" type="submit">{{ $announcement->is_published ? 'Unpublish' : 'Publish' }}</button>
                </form>
                <form class="inline-form" method="post" action="{{ route('admin.content.announcements.action') }}" onsubmit="return confirm('Delete this announcement?');">
                  @csrf
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="{{ $announcement->id }}">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>
</div>
@endsection
