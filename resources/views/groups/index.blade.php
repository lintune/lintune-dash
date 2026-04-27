@extends('layouts.app')

@section('title', 'Groups')
@section('page-title', 'Groups')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title me-auto">Group List</h3>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
      <i class="bi bi-plus-circle me-1"></i>New Group
    </button>
  </div>
  <div class="card-body p-0">
    @if (session('success'))
      <div class="alert alert-success m-3">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger m-3">{{ $errors->first() }}</div>
    @endif

    @if ($groups->isEmpty())
      <p class="text-center p-4 text-muted">No groups yet. Create one to get started.</p>
    @else
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Email / Identifier</th>
            <th>Members</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($groups as $group)
          <tr>
            <td><strong>{{ $group->name }}</strong></td>
            <td>
              @if ($group->type === 'mailing_list')
                <span class="badge bg-info text-dark"><i class="bi bi-envelope me-1"></i>Mailing List</span>
              @else
                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Security Group</span>
              @endif
            </td>
            <td class="text-muted small">
              {{ $group->type === 'mailing_list' ? $group->email : $group->slug }}
            </td>
            <td>{{ $group->members_count }}</td>
            <td class="text-end">
              <a href="{{ route('groups.show', $group->id) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Manage
              </a>
              <form method="POST" action="{{ route('groups.destroy', $group->id) }}" class="d-inline"
                    onsubmit="return confirm('Delete group \"{{ $group->name }}\"? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('groups.store') }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">New Group</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Group Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Marketing Team" required value="{{ old('name') }}">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Type</label>
            <div class="d-flex gap-4">
              @if ($mailcowEnabled)
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" id="typeMailing" value="mailing_list"
                       {{ old('type', 'mailing_list') === 'mailing_list' ? 'checked' : '' }}>
                <label class="form-check-label" for="typeMailing">
                  <i class="bi bi-envelope me-1 text-info"></i>Mailing List
                </label>
              </div>
              @endif
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" id="typeSecurity" value="security"
                       {{ (!$mailcowEnabled || old('type') === 'security') ? 'checked' : '' }}>
                <label class="form-check-label" for="typeSecurity">
                  <i class="bi bi-shield-check me-1 text-success"></i>Security Group
                </label>
              </div>
            </div>
            <small class="text-muted">
              Mailing lists use Mailcow aliases. Security groups sync to Keycloak{{ $nextcloudEnabled ? ' and Nextcloud' : '' }}.
            </small>
          </div>

          <div class="mb-3" id="emailField" style="{{ (!$mailcowEnabled || old('type') === 'security') ? 'display:none' : '' }}">
            <label class="form-label fw-semibold">Email Address</label>
            <div class="input-group">
              <input type="text" name="local_part" class="form-control" placeholder="e.g. marketing"
                     value="{{ old('local_part') }}">
              <span class="input-group-text">&#64;{{ session('realm') }}</span>
            </div>
            <small class="text-muted">The email address users send to for this mailing list.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Group</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const radios = document.querySelectorAll('input[name="type"]');
  const emailField = document.getElementById('emailField');

  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      emailField.style.display = this.value === 'mailing_list' ? '' : 'none';
    });
  });

  // Re-open modal with errors
  @if ($errors->any())
  var modal = new bootstrap.Modal(document.getElementById('createGroupModal'));
  modal.show();
  @endif
})();
</script>
@endsection
