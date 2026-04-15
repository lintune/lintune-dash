@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'Users')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title me-auto">User List</h3>
    <input type="search" id="search" class="form-control w-auto" placeholder="Search…" />
  </div>
  <div class="card-body p-0">
    @if ($users === null)
      <div class="alert alert-danger m-3">Could not load users.</div>
    @elseif (empty($users))
      <p class="text-center p-4 text-muted">No users found.</p>
    @else
      <table class="table table-striped table-hover mb-0" id="users-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Username</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($users as $user)
          <tr>
            <td>{{ trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: '–' }}</td>
            <td>{{ $user['email'] ?? '–' }}</td>
            <td>{{ $user['username'] ?? '–' }}</td>
            <td>
              <span class="badge text-bg-{{ ($user['enabled'] ?? false) ? 'success' : 'secondary' }}">
                {{ ($user['enabled'] ?? false) ? 'Active' : 'Disabled' }}
              </span>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<script>
  document.getElementById('search')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#users-table tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>
@endsection
