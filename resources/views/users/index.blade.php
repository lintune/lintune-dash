@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'Users')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title me-auto">User List</h3>
    <div class="me-3 d-flex gap-3">
      <small class="text-muted">
        <i class="bi bi-people me-1"></i>
        {{ $counts['users'] }}{{ $counts['max_users'] ? '/'.$counts['max_users'] : '' }} users
      </small>
      @if($mailcowEnabled)
      <small class="text-muted">
        <i class="bi bi-envelope me-1"></i>
        {{ $counts['mailboxes'] }}{{ $counts['max_mailboxes'] ? '/'.$counts['max_mailboxes'] : '' }} mailboxes
      </small>
      @endif
      @if($nextcloudEnabled)
      <small class="text-muted">
        <i class="bi bi-cloud me-1"></i>
        {{ $counts['nextcloud'] }}{{ $counts['max_nextcloud'] ? '/'.$counts['max_nextcloud'] : '' }} Nextcloud
      </small>
      @endif
    </div>
    <input type="search" id="search" class="form-control w-auto me-2" placeholder="Search…" />
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="create">
      <i class="bi bi-person-plus me-1"></i>New User
    </button>
  </div>
  <div class="card-body p-0">
    @if (session('success'))
      <div class="alert alert-success m-3">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger m-3">{{ $errors->first() }}</div>
    @endif

    @if ($users === null)
      <div class="alert alert-danger m-3">Could not load users.</div>
    @elseif (empty($users))
      <p class="text-center p-4 text-muted">No users found.</p>
    @else
      <table class="table table-striped table-hover mb-0 align-middle" id="users-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Active</th>
            @if($mailcowEnabled)<th>Mailbox</th>@endif
            @if($nextcloudEnabled)<th>Nextcloud</th>@endif
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($users as $user)
          @php
            $enabled    = $user['enabled'] ?? false;
            $isAdmin    = in_array($user['id'], $adminUserIds);
            $userEmail  = $user['email'] ?? '';
            $hasMailbox = $mailcowEnabled && isset($mailboxEmails[$userEmail]);
          @endphp
          <tr>
            <td>{{ trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: '–' }}</td>
            <td>{{ $userEmail ?: '–' }}</td>
            <td>
              @if ($isAdmin)
                <span class="badge text-bg-dark"><i class="bi bi-shield-check me-1"></i>Realm Admin</span>
              @else
                <span class="text-muted small">User</span>
              @endif
            </td>
            <td>
              <form method="POST" action="{{ route('users.toggle', $user['id']) }}" class="toggle-form">
                @csrf
                <div class="form-check form-switch mb-0" style="padding-left:0">
                  <input type="checkbox" class="form-check-input toggle-switch" role="switch"
                    {{ $enabled ? 'checked' : '' }} title="{{ $enabled ? 'Disable user' : 'Enable user' }}"
                    style="margin-left:0;cursor:pointer">
                </div>
              </form>
            </td>
            @if($mailcowEnabled)
            <td>
              <form method="POST" action="{{ route('users.toggle-mailbox', $user['id']) }}" class="toggle-form">
                @csrf
                <div class="form-check form-switch mb-0" style="padding-left:0">
                  <input type="checkbox" class="form-check-input toggle-switch" role="switch"
                    {{ $hasMailbox ? 'checked' : '' }} title="{{ $hasMailbox ? 'Remove mailbox' : 'Create mailbox' }}"
                    style="margin-left:0;cursor:pointer">
                </div>
              </form>
            </td>
            @endif
            @if($nextcloudEnabled)
            @php $hasNextcloud = isset($ncUserIds[$userEmail]); @endphp
            <td>
              <form method="POST" action="{{ route('users.toggle-nextcloud', $user['id']) }}" class="toggle-form">
                @csrf
                <div class="form-check form-switch mb-0" style="padding-left:0">
                  <input type="checkbox" class="form-check-input toggle-switch" role="switch"
                    {{ $hasNextcloud ? 'checked' : '' }} title="{{ $hasNextcloud ? 'Remove Nextcloud access' : 'Enable Nextcloud access' }}"
                    style="margin-left:0;cursor:pointer">
                </div>
              </form>
            </td>
            @endif
            <td class="text-end pe-3">
              <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                data-bs-toggle="modal" data-bs-target="#userModal"
                data-mode="edit"
                data-id="{{ $user['id'] }}"
                data-firstname="{{ $user['firstName'] ?? '' }}"
                data-lastname="{{ $user['lastName'] ?? '' }}"
                data-email="{{ $user['email'] ?? '' }}"
                data-username="{{ $user['username'] ?? '' }}"
                data-admin="{{ $isAdmin ? '1' : '0' }}">
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-danger"
                data-bs-toggle="modal" data-bs-target="#confirmModal"
                data-id="{{ $user['id'] }}"
                data-name="{{ trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: $user['email'] }}"
                data-email="{{ $userEmail }}"
                data-has-mailbox="{{ $hasMailbox ? '1' : '0' }}"
                data-has-nextcloud="{{ isset($ncUserIds[$userEmail]) ? '1' : '0' }}">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

{{-- Create / Edit modal --}}
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="userForm" method="POST">
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST">
        <div class="modal-body">
          <div class="row mb-3">
            <div class="col">
              <label class="form-label">First name</label>
              <input type="text" name="firstName" id="fieldFirstName" class="form-control" required />
            </div>
            <div class="col">
              <label class="form-label">Last name</label>
              <input type="text" name="lastName" id="fieldLastName" class="form-control" required />
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <div class="input-group" id="emailCreateGroup">
              <input type="text" name="email" id="fieldEmail" class="form-control"
                     pattern="[a-zA-Z0-9_.\-]+" />
              <span class="input-group-text">{{ '@' . $realm }}</span>
            </div>
            <div id="emailEditGroup" style="display:none">
              <input type="email" name="email" id="fieldEmailEdit" class="form-control" />
            </div>
            <small class="text-muted" id="emailHint"></small>
          </div>
          <div class="mb-3" id="usernameRow" style="display:none">
            <label class="form-label">Username</label>
            <input type="text" id="fieldUsername" class="form-control" readonly />
            <small class="text-muted">Username cannot be changed.</small>
          </div>
          <div class="mb-3">
            <label class="form-label" id="passwordLabel">Password</label>
            <div class="input-group">
              <input type="text" name="password" id="fieldPassword" class="form-control" minlength="8" />
              <button type="button" class="btn btn-outline-secondary" id="generatePassword" title="Generate password">
                <i class="bi bi-arrow-repeat"></i>
              </button>
            </div>
            <small class="text-muted" id="passwordHint"></small>
          </div>
          <div class="form-check">
            <input type="checkbox" name="is_admin" id="fieldIsAdmin" class="form-check-input" value="1" />
            <label class="form-check-label" for="fieldIsAdmin">
              <i class="bi bi-shield-check me-1"></i>Realm Admin
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="userSubmitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Confirm modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form id="confirmForm" method="POST">
          @csrf
          <input type="hidden" name="_method" id="confirmMethod">
          <button type="submit" class="btn btn-danger" id="confirmBtn">Yes, delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle switches — submit parent form on change
document.querySelectorAll('.toggle-switch').forEach(function (input) {
  input.addEventListener('change', function () {
    document.getElementById('loadingOverlay').style.display = 'flex';
    this.closest('form').submit();
  });
});

// Search
document.getElementById('search')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#users-table tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Password generator
function generatePassword() {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
  return Array.from(crypto.getRandomValues(new Uint8Array(16)))
    .map(b => chars[b % chars.length]).join('');
}

document.getElementById('generatePassword').addEventListener('click', function () {
  document.getElementById('fieldPassword').value = generatePassword();
});

// User modal (create / edit)
document.getElementById('userModal').addEventListener('show.bs.modal', function (e) {
  const btn    = e.relatedTarget;
  const mode   = btn.dataset.mode;
  const form   = document.getElementById('userForm');
  const method = document.getElementById('formMethod');
  const hint   = document.getElementById('passwordHint');
  const pwdField = document.getElementById('fieldPassword');

  document.getElementById('fieldFirstName').value = '';
  document.getElementById('fieldLastName').value  = '';
  document.getElementById('fieldEmail').value     = '';
  document.getElementById('fieldEmailEdit').value = '';
  document.getElementById('fieldUsername').value  = '';
  pwdField.value = '';
  document.getElementById('fieldIsAdmin').checked = false;

  if (mode === 'create') {
    form.action  = '{{ route("users.store") }}';
    method.value = 'POST';
    this.querySelector('.modal-title').textContent = 'New User';
    pwdField.required = true;
    hint.textContent  = 'Required.';
    document.getElementById('emailHint').textContent = 'Username will be the full email address and cannot be changed later.';
    document.getElementById('emailCreateGroup').style.display = '';
    document.getElementById('emailEditGroup').style.display   = 'none';
    document.getElementById('fieldEmail').name     = 'email';
    document.getElementById('fieldEmail').required = true;
    document.getElementById('fieldEmailEdit').name = '';
    document.getElementById('fieldEmailEdit').required = false;
    document.getElementById('usernameRow').style.display = 'none';
    document.getElementById('userSubmitBtn').textContent = 'Create User';
    pwdField.value = generatePassword();
  } else {
    const id = btn.dataset.id;
    form.action  = `/users/${id}`;
    method.value = 'PUT';
    this.querySelector('.modal-title').textContent = 'Edit User';
    document.getElementById('fieldFirstName').value = btn.dataset.firstname;
    document.getElementById('fieldLastName').value  = btn.dataset.lastname;
    document.getElementById('fieldEmailEdit').value = btn.dataset.email;
    document.getElementById('fieldUsername').value  = btn.dataset.username;
    document.getElementById('fieldIsAdmin').checked = btn.dataset.admin === '1';
    document.getElementById('emailHint').textContent = '';
    document.getElementById('emailCreateGroup').style.display = 'none';
    document.getElementById('emailEditGroup').style.display   = '';
    document.getElementById('fieldEmail').name     = '';
    document.getElementById('fieldEmail').required = false;
    document.getElementById('fieldEmailEdit').name = 'email';
    document.getElementById('fieldEmailEdit').required = true;
    document.getElementById('usernameRow').style.display = '';
    pwdField.required = false;
    hint.textContent  = 'Leave blank to keep current password.';
    document.getElementById('userSubmitBtn').textContent = 'Save Changes';
  }
});

// Confirm modal (delete only)
document.getElementById('confirmModal').addEventListener('show.bs.modal', function (e) {
  const btn         = e.relatedTarget;
  const id          = btn.dataset.id;
  const name        = btn.dataset.name;
  const hasMailbox  = btn.dataset.hasMailbox === '1';
  const hasNextcloud = btn.dataset.hasNextcloud === '1';
  const form        = document.getElementById('confirmForm');

  form.action = `/users/${id}`;
  document.getElementById('confirmMethod').value = 'DELETE';
  document.getElementById('confirmTitle').textContent = `Delete "${name}"`;

  let extra = '';
  if (hasMailbox || hasNextcloud) {
    const services = [];
    if (hasMailbox)   services.push('mailbox');
    if (hasNextcloud) services.push('Nextcloud account');
    extra = `<div class="alert alert-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
      This will also delete their ${services.join(' and ')}.</div>`;
  }

  document.getElementById('confirmBody').innerHTML =
    `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
     This will permanently delete the user. This cannot be undone.</div>${extra}`;
});
</script>
<div id="loadingOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;flex-direction:column;align-items:center;justify-content:center;gap:1rem">
  <div class="spinner-border text-light" style="width:3rem;height:3rem"></div>
  <span class="text-white fw-semibold fs-6">Processing…</span>
</div>

@endsection
