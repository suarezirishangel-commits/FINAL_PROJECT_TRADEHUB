@foreach (['success', 'error', 'info'] as $type)
  @if (session()->has($type))
    <div class="container flash-wrap">
      <div class="flash flash-{{ $type }}" role="status">{{ session($type) }}</div>
    </div>
  @endif
@endforeach
@if ($errors->any())
  <div class="container flash-wrap">
    <div class="errors" role="alert">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif
