@extends('layouts.admin', ['pageTitle' => 'WordPress content', 'active' => 'admin.content'])

@section('content')
<div class="admin-topbar">
  <div><h1>WordPress content management</h1><p>WordPress na ang source ng homepage copy at announcements ng storefront.</p></div>
</div>
<section class="card">
  <div class="card-head"><h2>Buksan ang WordPress CMS</h2></div>
  <p>Gamitin ang WordPress dashboard para baguhin ang site name, tagline, homepage text, contact details, at announcements.</p>
  <a class="btn btn-primary" href="{{ $wordpressAdminUrl }}" target="_blank" rel="noopener noreferrer">Manage content in WordPress</a>
</section>
@endsection
