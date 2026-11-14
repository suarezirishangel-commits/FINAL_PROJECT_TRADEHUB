@extends('layouts.app', ['pageTitle' => 'Create account', 'active' => ''])

@section('content')
<div class="container auth-wrap">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="sub">Buy from local sellers and track every order in one place.</p>
    <form method="post" action="{{ route('register.submit') }}" novalidate>
      @csrf
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="120" autocomplete="name">
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
      </div>
      <div class="field">
        <label for="phone">Contact number <span class="hint">(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel">
      </div>
      <div class="field">
        <label for="address">Delivery address <span class="hint">(optional)</span></label>
        <input type="text" id="address" name="address" value="{{ old('address') }}" maxlength="255" autocomplete="street-address">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <p class="hint">At least 8 characters, with one letter and one number.</p>
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
      </div>
      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="auth-foot">Already registered? <a href="{{ route('login') }}">Log in</a></p>
  </div>
</div>
@endsection
