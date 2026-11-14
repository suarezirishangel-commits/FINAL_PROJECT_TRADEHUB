<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle }} · {{ $siteContent->setting('site_name', 'TradeHub') }} Admin</title>
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-side">
    <a class="brand brand-light" href="{{ route('admin.dashboard') }}">
      <span class="brand-mark">TH</span>
      <span class="brand-name">{{ $siteContent->setting('site_name', 'TradeHub') }}<small>Admin panel</small></span>
    </a>
    <nav aria-label="Admin navigation">
      @foreach ([
          'admin.dashboard' => 'Dashboard',
          'admin.products' => 'Products',
          'admin.categories' => 'Categories',
          'admin.orders' => 'Orders',
          'admin.users' => 'Users',
          'admin.content' => 'Content & announcements',
          'admin.reports' => 'Sales reports',
      ] as $routeName => $label)
        <a href="{{ route($routeName) }}" class="{{ ($active ?? '') === $routeName ? 'is-active' : '' }}">{{ $label }}</a>
      @endforeach
      <div class="side-sep"></div>
      <a href="{{ route('home') }}">View storefront</a>
      <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="side-link">Log out</button>
      </form>
    </nav>
  </aside>
  <div class="admin-main">
    @include('partials.flash')
    @yield('content')
  </div>
</div>
</body>
</html>
