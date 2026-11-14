@extends('layouts.admin', ['pageTitle' => 'Sales reports', 'active' => 'admin.reports'])

@section('content')
@php($maxCategory = (float) $categoryRows->max('revenue'))
<div class="admin-topbar">
  <div><h1>Sales reports</h1><p>Sales, commission, and best sellers for a date range.</p></div>
  <form method="get" action="{{ route('admin.reports') }}" class="admin-actions" style="align-items:flex-end">
    <div class="field" style="margin:0"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}" required></div>
    <div class="field" style="margin:0"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}" required></div>
    <button class="btn btn-primary" type="submit">Generate</button>
  </form>
</div>
<div class="stat-grid">
  <div class="stat"><span>Gross sales</span><strong>{{ \App\Support\TradeHubPresenter::price($summary['gross']) }}</strong><small>{{ $summary['orders'] }} orders, {{ $summary['units'] }} units</small></div>
  <div class="stat"><span>Platform commission</span><strong>{{ \App\Support\TradeHubPresenter::price($commission) }}</strong><small>{{ number_format($rate, 2) }}% of gross</small></div>
  <div class="stat"><span>Seller payout</span><strong>{{ \App\Support\TradeHubPresenter::price($summary['gross'] - $commission) }}</strong><small>After commission</small></div>
  <div class="stat"><span>Average order</span><strong>{{ \App\Support\TradeHubPresenter::price($avgOrder) }}</strong><small>{{ $from }} to {{ $to }}</small></div>
</div>
<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Sales by category</h2></div>
    @forelse ($categoryRows as $row)
      <div style="margin-bottom:.9rem">
        <div style="display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:.3rem">
          <span>{{ $row->name }} <small class="hint">({{ $row->units }} units)</small></span>
          <strong>{{ \App\Support\TradeHubPresenter::price($row->revenue) }}</strong>
        </div>
        <div style="height:8px;background:#eef2f1;border-radius:999px;overflow:hidden">
          <div style="height:100%;width:{{ $maxCategory > 0 ? round((float) $row->revenue / $maxCategory * 100) : 0 }}%;background:var(--brand)"></div>
        </div>
      </div>
    @empty
      <p class="hint">No sales recorded in this period.</p>
    @endforelse
  </section>
  <section class="card">
    <div class="card-head"><h2>Top products</h2></div>
    @forelse ($topRows as $row)
      <ul style="list-style:none;padding:0;margin:0">
        <li style="display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
          <span>{{ $row->product_name }}<br><small class="hint">{{ $row->units }} sold</small></span>
          <strong>{{ \App\Support\TradeHubPresenter::price($row->revenue) }}</strong>
        </li>
      </ul>
    @empty
      <p class="hint">Nothing sold in this range yet.</p>
    @endforelse
  </section>
</div>
<section class="card" style="margin-top:1.25rem">
  <div class="card-head"><h2>Transaction log</h2><small class="hint">Latest 50 records in range</small></div>
  <div class="table-wrap" style="box-shadow:none">
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Item</th><th class="num">Qty</th><th class="num">Amount</th><th>Payment</th><th>Status</th></tr></thead>
      <tbody>
      @forelse ($logRows as $row)
        <tr>
          <td>{{ $row->created_at?->format('M j, Y') }}<br><small class="hint">{{ $row->created_at?->format('g:i A') }}</small></td>
          <td>{{ $row->reference }}</td>
          <td>{{ $row->user->full_name }}</td>
          <td>{{ $row->product_name }}</td>
          <td class="num">{{ $row->quantity }}</td>
          <td class="num">{{ \App\Support\TradeHubPresenter::price($row->total_amount) }}</td>
          <td>{{ strtoupper($row->payment_method) }}</td>
          <td><span class="badge badge-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="8" class="hint">No transactions in this date range.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
