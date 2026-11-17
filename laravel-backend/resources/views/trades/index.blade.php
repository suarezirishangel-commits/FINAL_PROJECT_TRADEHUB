@extends('layouts.app', ['pageTitle' => 'Trades', 'active' => 'trades'])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Community exchange</div>
    <h1>Trade &amp; Swap</h1>
    <p>Offer an item in exchange for another item. No cash payments are part of these trades.</p>
  </div>

  <section class="card trade-guide" aria-labelledby="trade-guide-title">
    <h2 id="trade-guide-title">How to accept a trade offer</h2>
    <ol>
      <li>Go to <strong>My listings</strong> and click <strong>Manage</strong> beside your item.</li>
      <li>Under <strong>Trade offers</strong>, review the offered item and click <strong>Accept offer</strong> on your choice. Other pending offers will be declined.</li>
      <li>After exchanging the items, both users must click <strong>Confirm handoff</strong>.</li>
    </ol>
  </section>

  @auth
    <section class="card trade-create">
      <div class="card-head"><h2>List an item to trade</h2></div>
      <form method="post" action="{{ route('trades.store') }}" class="form-grid">
        @csrf
        <div class="field span-2">
          <label for="trade-title">Item you have</label>
          <input id="trade-title" name="title" value="{{ old('title') }}" maxlength="120" required>
        </div>
        <div class="field span-2">
          <label for="trade-description">Description</label>
          <textarea id="trade-description" name="description" maxlength="2000" required>{{ old('description') }}</textarea>
        </div>
        <div class="field span-2">
          <label for="trade-wanted">What would you like in exchange?</label>
          <input id="trade-wanted" name="wanted_item" value="{{ old('wanted_item') }}" maxlength="255" required>
        </div>
        <div class="field span-2">
          <button class="btn btn-primary" type="submit">Post trade listing</button>
        </div>
      </form>
    </section>
  @else
    <p class="notice">Log in to list an item or make a trade offer. <a href="{{ route('login') }}">Log in</a></p>
  @endauth

  <section class="section">
    <div class="section-head"><div><div class="kicker">Available now</div><h2>Open listings</h2></div></div>
    @if ($listings->isEmpty())
      <p class="hint">No open trade listings right now.</p>
    @else
      <div class="trade-grid">
        @foreach ($listings as $listing)
          <article class="card trade-card">
            <div class="kicker">Offered by {{ $listing->owner->full_name }}</div>
            <h3>{{ $listing->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($listing->description, 160) }}</p>
            <p><strong>Looking for:</strong> {{ $listing->wanted_item }}</p>
            <a class="btn btn-outline btn-sm" href="{{ route('trades.show', $listing->id) }}">View listing / offer a trade</a>
          </article>
        @endforeach
      </div>
      {{ $listings->links() }}
    @endif
  </section>

  @auth
    <section class="section">
      <div class="section-head"><div><div class="kicker">Your items</div><h2>My listings</h2></div></div>
      @forelse ($myListings as $listing)
        <article class="trade-row">
          <div>
            <strong><a href="{{ route('trades.show', $listing->id) }}">{{ $listing->title }}</a></strong>
            <p class="hint">{{ $listing->wanted_item }} · {{ $listing->offers_count }} offers · {{ ucfirst(str_replace('_', ' ', $listing->status)) }}</p>
          </div>
          <a class="btn btn-outline btn-sm" href="{{ route('trades.show', $listing->id) }}">Manage</a>
        </article>
      @empty
        <p class="hint">You have not listed an item yet.</p>
      @endforelse
    </section>

    <section class="section">
      <div class="section-head"><div><div class="kicker">Offers on your listings</div><h2>Incoming offers</h2></div></div>
      <p class="hint">To accept an offer, select <strong>Review</strong> beside it, then choose <strong>Accept offer</strong> on the listing page. After the exchange, both users must confirm the handoff.</p>
      @forelse ($incomingOffers as $offer)
        <article class="trade-row">
          <div>
            <strong>{{ $offer->requester->full_name }} offered {{ $offer->offered_item }}</strong>
            <p class="hint">For <a href="{{ route('trades.show', $offer->listing_id) }}">{{ $offer->listing->title }}</a> · {{ ucfirst($offer->status) }}</p>
          </div>
          <a class="btn btn-outline btn-sm" href="{{ route('trades.show', $offer->listing_id) }}">Review</a>
        </article>
      @empty
        <p class="hint">No one has made an offer yet.</p>
      @endforelse
    </section>

    <section class="section">
      <div class="section-head"><div><div class="kicker">Your offers</div><h2>My requests</h2></div></div>
      @forelse ($myOffers as $offer)
        <article class="trade-row">
          <div>
            <strong>You offered {{ $offer->offered_item }}</strong>
            <p class="hint">For <a href="{{ route('trades.show', $offer->listing_id) }}">{{ $offer->listing->title }}</a> · {{ ucfirst($offer->status) }}</p>
          </div>
          <a class="btn btn-outline btn-sm" href="{{ route('trades.show', $offer->listing_id) }}">View</a>
        </article>
      @empty
        <p class="hint">You have not made an offer yet.</p>
      @endforelse
    </section>
  @endauth
</div>
@endsection
