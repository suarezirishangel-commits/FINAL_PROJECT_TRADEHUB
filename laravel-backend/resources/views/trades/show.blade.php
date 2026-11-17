@extends('layouts.app', ['pageTitle' => $listing->title, 'active' => 'trades'])

@section('content')
<div class="container">
  <p class="hint" style="padding-top:1.5rem"><a href="{{ route('trades.index') }}">Trades</a> / {{ $listing->title }}</p>
  <section class="card trade-detail">
    <div class="kicker">Listed by {{ $listing->owner->full_name }} · {{ ucfirst(str_replace('_', ' ', $listing->status)) }}</div>
    <h1>{{ $listing->title }}</h1>
    <p>{{ $listing->description }}</p>
    <p><strong>Looking for:</strong> {{ $listing->wanted_item }}</p>
    <p class="hint">This is an item-for-item exchange. Cash top-ups are not supported.</p>

    @auth
      @if ((int) $listing->user_id === (int) auth()->id())
        @if ($listing->status === 'open')
          <form method="post" action="{{ route('trades.cancel', $listing->id) }}">
            @csrf
            <button class="btn btn-outline" type="submit">Cancel listing</button>
          </form>
        @endif
      @elseif ($listing->status === 'open' && (!$myOffer || in_array($myOffer->status, ['declined', 'cancelled'], true)))
        <form class="trade-offer-form" method="post" action="{{ route('trades.offers.store', $listing->id) }}">
          @csrf
          <h2>Make an offer</h2>
          <div class="field">
            <label for="offered-item">Item you will exchange</label>
            <input id="offered-item" name="offered_item" maxlength="120" value="{{ old('offered_item') }}" required>
          </div>
          <div class="field">
            <label for="offer-message">Message (optional)</label>
            <textarea id="offer-message" name="message" maxlength="500">{{ old('message') }}</textarea>
          </div>
          <button class="btn btn-primary" type="submit">Send trade offer</button>
        </form>
      @endif
    @else
      @if ($listing->status === 'open')
        <a class="btn btn-primary" href="{{ route('login') }}">Log in to make an offer</a>
      @endif
    @endauth
  </section>

  @auth
    @if ((int) $listing->user_id === (int) auth()->id())
      <section class="card trade-detail">
        <h2>How to accept an offer</h2>
        <ol>
          <li>Review the offered item and message under <strong>Trade offers</strong> below.</li>
          <li>Click <strong>Accept offer</strong> on the offer you want. Other pending offers will be declined.</li>
          <li>After exchanging the items, you and the other user must each click <strong>Confirm handoff</strong>.</li>
        </ol>
        <p class="hint">The trade is complete only after both users confirm the handoff.</p>
      </section>
      <section class="section">
        <div class="section-head"><div><div class="kicker">Responses</div><h2>Trade offers</h2></div></div>
        @forelse ($offers as $offer)
          <article class="card trade-offer-card">
            <div class="review-heading">
              <strong>{{ $offer->requester->full_name }} offers {{ $offer->offered_item }}</strong>
              <span>{{ ucfirst($offer->status) }}</span>
            </div>
            @if ($offer->message)
              <p>{{ $offer->message }}</p>
            @endif
            @if ($offer->status === 'pending' && $listing->status === 'open')
              <div class="row-actions">
                <form method="post" action="{{ route('trade-offers.accept', $offer->id) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Accept offer</button></form>
                <form method="post" action="{{ route('trade-offers.decline', $offer->id) }}">@csrf<button class="btn btn-outline btn-sm" type="submit">Decline</button></form>
              </div>
            @elseif ($offer->status === 'accepted')
              <p class="hint">Owner confirmation: {{ $offer->owner_confirmed_at ? 'confirmed' : 'waiting' }} · Requester confirmation: {{ $offer->requester_confirmed_at ? 'confirmed' : 'waiting' }}</p>
              @if (!$offer->owner_confirmed_at)
                <form method="post" action="{{ route('trade-offers.confirm', $offer->id) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Confirm handoff</button></form>
              @endif
            @elseif ($offer->status === 'completed')
              <p class="hint">Both users confirmed the exchange.</p>
            @endif
          </article>
        @empty
          <p class="hint">No offers on this listing yet.</p>
        @endforelse
      </section>
    @elseif ($myOffer)
      <section class="section">
        <div class="section-head"><div><div class="kicker">Your response</div><h2>My trade offer</h2></div></div>
        <article class="card trade-offer-card">
          <p><strong>You offered:</strong> {{ $myOffer->offered_item }}</p>
          @if ($myOffer->message)<p>{{ $myOffer->message }}</p>@endif
          <p><strong>Status:</strong> {{ ucfirst($myOffer->status) }}</p>
          @if ($myOffer->status === 'pending' && $listing->status === 'open')
            <form method="post" action="{{ route('trade-offers.cancel', $myOffer->id) }}">@csrf<button class="btn btn-outline btn-sm" type="submit">Cancel offer</button></form>
          @elseif ($myOffer->status === 'accepted')
            <p class="hint">Owner confirmation: {{ $myOffer->owner_confirmed_at ? 'confirmed' : 'waiting' }} · Your confirmation: {{ $myOffer->requester_confirmed_at ? 'confirmed' : 'waiting' }}</p>
            @if (!$myOffer->requester_confirmed_at)
              <form method="post" action="{{ route('trade-offers.confirm', $myOffer->id) }}">@csrf<button class="btn btn-primary" type="submit">Confirm handoff</button></form>
            @endif
          @elseif ($myOffer->status === 'completed')
            <p class="hint">Both users confirmed the exchange.</p>
          @endif
        </article>
      </section>
    @endif
  @endauth
</div>
@endsection
