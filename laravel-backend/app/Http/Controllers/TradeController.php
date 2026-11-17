<?php

namespace App\Http\Controllers;

use App\Models\TradeListing;
use App\Models\TradeOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TradeController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()?->id;
        $listings = TradeListing::query()
            ->with('owner')
            ->where('status', 'open')
            ->whereHas('owner', fn ($query) => $query->where('status', 'active'))
            ->when($userId !== null, fn ($query) => $query->where('user_id', '<>', $userId))
            ->orderByDesc('created_at')
            ->paginate(12);
        $myListings = collect();
        $incomingOffers = collect();
        $myOffers = collect();

        if ($userId !== null) {
            $myListings = TradeListing::query()
                ->withCount('offers')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->get();
            $incomingOffers = TradeOffer::query()
                ->with(['listing.owner', 'requester'])
                ->whereHas('listing', fn ($query) => $query->where('user_id', $userId))
                ->orderByDesc('created_at')
                ->get();
            $myOffers = TradeOffer::query()
                ->with(['listing.owner'])
                ->where('requester_id', $userId)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('trades.index', compact('listings', 'myListings', 'incomingOffers', 'myOffers'));
    }

    public function show(Request $request, int $listingId): View
    {
        $listing = TradeListing::query()->with('owner')->findOrFail($listingId);
        $offers = collect();
        $myOffer = null;
        $userId = $request->user()?->id;

        if ($userId !== null && $listing->user_id === $userId) {
            $offers = $listing->offers()->with('requester')->orderByDesc('created_at')->get();
        } elseif ($userId !== null) {
            $myOffer = $listing->offers()
                ->where('requester_id', $userId)
                ->orderByDesc('created_at')
                ->first();
        }

        return view('trades.show', compact('listing', 'offers', 'myOffer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'wanted_item' => ['required', 'string', 'min:2', 'max:255'],
        ]);
        $listing = TradeListing::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return redirect()->route('trades.show', $listing->id)->with('success', 'Trade listing created.');
    }

    public function offer(Request $request, int $listingId): RedirectResponse
    {
        $data = $request->validate([
            'offered_item' => ['required', 'string', 'min:2', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $userId = $request->user()->id;

        $created = DB::transaction(function () use ($data, $listingId, $userId): bool {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            abort_unless($listing->user_id !== $userId, 403);
            abort_unless($listing->status === 'open', 409, 'This listing is no longer open.');

            $existingOffer = $listing->offers()
                ->where('requester_id', $userId)
                ->whereIn('status', ['pending', 'accepted', 'completed'])
                ->exists();
            if ($existingOffer) {
                return false;
            }

            $listing->offers()->create([
                'requester_id' => $userId,
                'offered_item' => $data['offered_item'],
                'message' => ($data['message'] ?? '') ?: null,
                'status' => 'pending',
            ]);

            return true;
        });

        if (! $created) {
            return back()->withErrors(['offered_item' => 'You already have an active offer for this listing.']);
        }

        return back()->with('success', 'Your trade offer was sent.');
    }

    public function accept(Request $request, int $offerId): RedirectResponse
    {
        $listingId = TradeOffer::query()->whereKey($offerId)->value('listing_id');
        abort_if($listingId === null, 404);

        DB::transaction(function () use ($request, $offerId, $listingId): void {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            $offer = TradeOffer::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->findOrFail($offerId);
            abort_unless($listing->user_id === $request->user()->id, 403);
            abort_unless($listing->status === 'open' && $offer->status === 'pending', 409, 'This offer is no longer available.');

            $offer->update(['status' => 'accepted']);
            $listing->update(['status' => 'in_trade']);
            $listing->offers()
                ->whereKeyNot($offer->id)
                ->where('status', 'pending')
                ->update(['status' => 'declined']);
        });

        return redirect()->route('trades.show', $listingId)->with('success', 'Offer accepted. Both users must confirm the exchange after handoff.');
    }

    public function decline(Request $request, int $offerId): RedirectResponse
    {
        $listingId = TradeOffer::query()->whereKey($offerId)->value('listing_id');
        abort_if($listingId === null, 404);
        DB::transaction(function () use ($request, $offerId, $listingId): void {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            $offer = TradeOffer::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->findOrFail($offerId);
            abort_unless($listing->user_id === $request->user()->id, 403);
            abort_unless($listing->status === 'open' && $offer->status === 'pending', 409, 'This offer is no longer available.');
            $offer->update(['status' => 'declined']);
        });

        return redirect()->route('trades.show', $listingId)->with('success', 'Offer declined.');
    }

    public function cancelOffer(Request $request, int $offerId): RedirectResponse
    {
        $listingId = TradeOffer::query()->whereKey($offerId)->value('listing_id');
        abort_if($listingId === null, 404);
        DB::transaction(function () use ($request, $offerId, $listingId): void {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            $offer = TradeOffer::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->findOrFail($offerId);
            abort_unless($offer->requester_id === $request->user()->id, 403);
            abort_unless($listing->status === 'open' && $offer->status === 'pending', 409, 'This offer can no longer be cancelled.');
            $offer->update(['status' => 'cancelled']);
        });

        return redirect()->route('trades.show', $listingId)->with('success', 'Offer cancelled.');
    }

    public function cancelListing(Request $request, int $listingId): RedirectResponse
    {
        DB::transaction(function () use ($request, $listingId): void {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            abort_unless($listing->user_id === $request->user()->id, 403);
            abort_unless($listing->status === 'open', 409, 'Only open listings can be cancelled.');

            $listing->update(['status' => 'cancelled']);
            $listing->offers()->where('status', 'pending')->update(['status' => 'declined']);
        });

        return redirect()->route('trades.show', $listingId)->with('success', 'Listing cancelled.');
    }

    public function confirmHandoff(Request $request, int $offerId): RedirectResponse
    {
        $listingId = TradeOffer::query()->whereKey($offerId)->value('listing_id');
        abort_if($listingId === null, 404);

        DB::transaction(function () use ($request, $offerId, $listingId): void {
            $listing = TradeListing::query()->lockForUpdate()->findOrFail($listingId);
            $offer = TradeOffer::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->findOrFail($offerId);
            abort_unless($offer->status === 'accepted' && $listing->status === 'in_trade', 409, 'This exchange is not awaiting confirmation.');

            if ($listing->user_id === $request->user()->id) {
                $offer->owner_confirmed_at ??= now();
            } elseif ($offer->requester_id === $request->user()->id) {
                $offer->requester_confirmed_at ??= now();
            } else {
                abort(403);
            }
            $offer->save();

            if ($offer->owner_confirmed_at !== null && $offer->requester_confirmed_at !== null) {
                $offer->update(['status' => 'completed']);
                $listing->update(['status' => 'completed']);
            }
        });

        return redirect()->route('trades.show', $listingId)->with('success', 'Your handoff confirmation was recorded.');
    }
}
