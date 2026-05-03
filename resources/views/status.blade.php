@extends('layouts.app')

@section('title', 'Service Status')

@section('content')
<div class="row justify-content-center mt-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-activity"></i>
        <span class="fw-semibold">Service Status</span>
        <small class="text-muted ms-auto">Refreshed every 30 seconds</small>
      </div>
      <div class="card-body p-0">
        @if(empty($statuses))
          <div class="p-4 text-center text-muted">
            <i class="bi bi-question-circle fs-4 d-block mb-2"></i>
            Status information is not available right now.
          </div>
        @else
          <ul class="list-group list-group-flush" id="status-list">
            @foreach($statuses as $s)
              @php
                $color = match($s['status']) { 1 => 'success', 0 => 'danger', 3 => 'warning', default => 'secondary' };
                $label = match($s['status']) { 1 => 'Operational', 0 => 'Down', 3 => 'Maintenance', default => 'Unknown' };
                $icon  = match($s['status']) { 1 => 'check-circle-fill', 0 => 'x-circle-fill', 3 => 'tools', default => 'question-circle' };
              @endphp
              <li class="list-group-item d-flex align-items-center gap-3 py-3">
                <i class="bi bi-{{ $icon }} text-{{ $color }} fs-5"></i>
                <span class="fw-medium flex-grow-1">{{ $s['name'] }}</span>
                <span class="badge text-bg-{{ $color }}">{{ $label }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
setTimeout(function () { window.location.reload(); }, 30000);
</script>
@endpush
@endsection
