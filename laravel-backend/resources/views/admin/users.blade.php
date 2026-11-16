@extends('layouts.admin', ['pageTitle' => 'Users', 'active' => 'admin.users'])

@section('content')
<div class="admin-topbar">
  <div><h1>Users</h1><p>{{ $users->count() }} accounts. Administrators manage the marketplace; customers buy from it.</p></div>
</div>
<div class="table-wrap">
  <table class="data">
    <thead><tr><th>User</th><th>Contact</th><th>Role</th><th class="num">Orders</th><th class="num">Spent</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    @foreach ($users as $user)
      <tr>
        <td><strong>{{ $user->full_name }}</strong><br><small class="hint">{{ $user->email }}</small></td>
        <td>{{ $user->phone ?: '—' }}<br><small class="hint">{{ $user->address }}</small></td>
        <td>
          <form class="inline-form" method="post" action="{{ route('admin.users.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="role">
            <input type="hidden" name="id" value="{{ $user->id }}">
            <select name="role_id" onchange="this.form.submit()" @disabled((int) $user->id === (int) auth()->id())>
              @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((int) $user->role_id === (int) $role->id)>{{ $role->name }}</option>
              @endforeach
            </select>
          </form>
        </td>
        <td class="num">{{ $user->order_count }}</td>
        <td class="num">{{ \App\Support\TradeHubPresenter::price($user->spent) }}</td>
        <td><span class="badge {{ $user->status === 'active' ? 'badge-in' : 'badge-out' }}">{{ ucfirst($user->status) }}</span></td>
        <td>
          <form class="inline-form" method="post" action="{{ route('admin.users.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="{{ $user->id }}">
            <button class="btn btn-outline btn-sm" type="submit" @disabled((int) $user->id === (int) auth()->id())>{{ $user->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
          </form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
