@extends('layouts.app', ['pageTitle' => 'My profile', 'active' => ''])

@section('content')
<div class="container">
  <div class="page-head">
    <div class="kicker">Account</div>
    <h1>My profile</h1>
    <p>Update your delivery details and password.</p>
  </div>
  <div class="cart-layout">
    <form class="panel" method="post" action="{{ route('profile.update') }}">
      @csrf
      @method('PUT')
      <h2>Account details</h2>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" value="{{ $user->email }}" disabled>
      </div>
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" required maxlength="120">
      </div>
      <div class="field">
        <label for="phone">Contact number</label>
        <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30">
      </div>
      <div class="field">
        <label for="address">Delivery address</label>
        <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}" maxlength="255">
      </div>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
    <form class="panel" method="post" action="{{ route('profile.password') }}">
      @csrf
      @method('PUT')
      <h2>Change password</h2>
      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>
      <p class="hint">At least 8 characters, with one letter and one number.</p>
      <button class="btn btn-outline" type="submit">Update password</button>
    </form>
  </div>
</div>
@endsection
