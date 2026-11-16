@extends('layouts.app', ['pageTitle' => 'Log in', 'active' => ''])

@section('content')
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="sub">Log in to manage your cart, orders, and profile.</p>
    <form method="post" action="{{ route('login.submit') }}">
      @csrf
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>
    <p class="auth-foot">New to TradeHub? <a href="{{ route('register') }}">Create an account</a></p>
  </div>
</div>
@endsection
