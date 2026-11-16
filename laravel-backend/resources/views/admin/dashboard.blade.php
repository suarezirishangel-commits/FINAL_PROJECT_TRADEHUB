@extends('layouts.admin', ['pageTitle' => 'Dashboard', 'active' => 'admin.dashboard'])

@section('content')
<div class="admin-topbar">
  <div><h1>Dashboard</h1><p>Marketplace activity at a glance.</p></div>
  <div class="admin-actions">
    <a class="btn btn-outline" href="{{ route('admin.reports') }}">Sales report</a>
    <a class="btn btn-primary" href="{{ route('admin.products', ['action' => 'new']) }}">Add product</a>
  </div>
</div>
<div class="stat-grid">
  <div class="stat"><span>Total sales</span><strong>{{ \App\Support\TradeHubPresenter::price($totals['revenue']) }}</strong><small>{{ $totals['orders'] }} orders</small></div>
  <div class="stat"><span>Commission ({{ $rate }}%)</span><strong>{{ \App\Support\TradeHubPresenter::price($commission) }}</strong><small>Platform revenue</small></div>
  <div class="stat"><span>Active products</span><strong>{{ $counts['products'] }}</strong><small>{{ $totals['pending_lines'] }} pending line items</small></div>
  <div class="stat"><span>Customers</span><strong>{{ $counts['customers'] }}</strong><small>Registered buyers</small></div>
</div>
<div class="admin-grid">
  <section class="card">
    <div class="card-head"><h2>Recent transactions</h2><a class="btn btn-ghost btn-sm" href="{{ route('admin.reports') }}">View all</a></div>
    @if ($recent->isNotEmpty())
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Reference</th><th>Customer</th><th>Item</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
          @foreach ($recent as $row)
            <tr>
              <td>{{ $row->reference }}<br><small class="hint">{{ $row->created_at?->format('M j, g:i A') }}</small></td>
              <td>{{ $row->user->full_name }}</td>
              <td>{{ $row->product_name }} × {{ $row->quantity }}</td>
              <td class="num">{{ \App\Support\TradeHubPresenter::price($row->total_amount) }}</td>
              <td><span class="badge badge-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="hint">No transactions yet.</p>
    @endif
  </section>
  <section class="card">
    <div class="card-head"><h2>Low stock alerts</h2><a class="btn btn-ghost btn-sm" href="{{ route('admin.products') }}">Manage</a></div>
    @if ($lowStock->isNotEmpty())
      <ul style="list-style:none;padding:0;margin:0">
        @foreach ($lowStock as $item)
          <li style="display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:.9rem">
            <span>{{ $item->name }}<br><small class="hint">{{ $item->category->name }}</small></span>
            <span class="badge {{ $item->stock === 0 ? 'badge-out' : 'badge-low' }}">{{ $item->stock === 0 ? 'Out of stock' : $item->stock . ' left' }}</span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="hint">All active products have healthy stock levels.</p>
    @endif
  </section>
</div>
@endsection
