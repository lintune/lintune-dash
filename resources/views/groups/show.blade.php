@extends('layouts.app')

@section('title', $group->name)
@section('page-title', $group->name)

@section('content')

<div class="mb-3">
  <a href="{{ route('groups') }}" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back to Groups
  </a>
</div>

<!-- Group Info -->
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div>
        @if ($group->type === 'mailing_list')
          <span class="badge bg-info text-dark fs-6"><i class="bi bi-envelope me-1"></i>Mailing List</span>
        @else
          <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>Security Group</span>
        @endif
      </div>
      @if ($group->type === 'mailing_list' && $group->email)
        <div>
          <i class="bi bi-at text-muted me-1"></i>
          <strong>{{ $group->email }}</strong>
          <small class="text-muted ms-1">(Mailcow alias{{ $group->mailcow_alias_id ? '' : ' — no members yet' }})</small>
        </div>
      @endif
      @if ($group->type === 'security')
        <div>
          <i class="bi bi-key text-muted me-1"></i>
          <span class="text-muted small">Synced to Keycloak{{ $nextcloudEnabled && $group->nextcloud_id ? ' &amp; Nextcloud' : '' }}</span>
        </div>
      @endif
    </div>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<!-- Member Picker -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Manage Members</h3>
    <small class="text-muted ms-2">
      Select users and use the arrows to add or remove them, then click Save.
    </small>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('groups.sync-members', $group->id) }}" id="members-form">
      @csrf
      @method('PUT')

      <div class="row align-items-center g-3">

        <!-- Available users -->
        <div class="col-md-5">
          <label class="form-label fw-semibold text-muted small text-uppercase">
            Available Users
            @if ($group->type === 'mailing_list')
              <span class="text-info">(with mailbox)</span>
            @endif
          </label>
          <input type="text" id="search-available" class="form-control form-control-sm mb-2" placeholder="Search…">
          <select id="available" multiple class="form-select" style="height: 320px;">
            @foreach ($availableUsers->sortBy('name') as $user)
              <option value="{{ $user['id'] }}|{{ $user['email'] }}" data-label="{{ strtolower($user['name']) }} {{ strtolower($user['email']) }}">
                {{ $user['name'] ?: $user['email'] }} — {{ $user['email'] }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">{{ $availableUsers->count() }} available</small>
        </div>

        <!-- Arrow buttons -->
        <div class="col-md-2 d-flex flex-column align-items-center justify-content-center gap-3">
          <button type="button" id="btn-add" class="btn btn-primary w-100" title="Add selected">
            Add <i class="bi bi-arrow-right"></i>
          </button>
          <button type="button" id="btn-remove" class="btn btn-outline-secondary w-100" title="Remove selected">
            <i class="bi bi-arrow-left"></i> Remove
          </button>
        </div>

        <!-- Current members -->
        <div class="col-md-5">
          <label class="form-label fw-semibold text-muted small text-uppercase">Current Members</label>
          <input type="text" id="search-members" class="form-control form-control-sm mb-2" placeholder="Search…">
          <select id="members" multiple class="form-select" name="members[]" style="height: 320px;">
            @foreach ($membersWithNames->sortBy('name') as $member)
              <option value="{{ $member['user_id'] }}|{{ $member['email'] }}" data-label="{{ strtolower($member['name']) }} {{ strtolower($member['email']) }}">
                {{ $member['name'] ?: $member['email'] }} — {{ $member['email'] }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">{{ $membersWithNames->count() }} member(s)</small>
        </div>

      </div>

      <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('groups') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-lg me-1"></i>Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const available = document.getElementById('available');
  const members   = document.getElementById('members');
  const form      = document.getElementById('members-form');

  document.getElementById('btn-add').addEventListener('click', function () {
    Array.from(available.selectedOptions).forEach(function (opt) {
      members.appendChild(opt);
    });
    updateCounts();
  });

  document.getElementById('btn-remove').addEventListener('click', function () {
    Array.from(members.selectedOptions).forEach(function (opt) {
      available.appendChild(opt);
    });
    updateCounts();
  });

  // Before submit, select all options in members so they're included in POST
  form.addEventListener('submit', function () {
    Array.from(members.options).forEach(function (opt) {
      opt.selected = true;
    });
  });

  // Search filter for available
  document.getElementById('search-available').addEventListener('input', function () {
    filterSelect(available, this.value);
  });

  // Search filter for members
  document.getElementById('search-members').addEventListener('input', function () {
    filterSelect(members, this.value);
  });

  function filterSelect(select, query) {
    const q = query.toLowerCase();
    Array.from(select.options).forEach(function (opt) {
      opt.style.display = opt.dataset.label.includes(q) ? '' : 'none';
    });
  }

  function updateCounts() {
    document.querySelector('#available + small') &&
      (document.querySelectorAll('#available ~ small')[0].textContent = available.options.length + ' available');
    document.querySelector('#members ~ small') &&
      (document.querySelectorAll('#members ~ small')[0].textContent = members.options.length + ' member(s)');
  }
})();
</script>
@endsection
