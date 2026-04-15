<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lintune – Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc2/dist/css/adminlte.min.css" />
</head>
<body class="login-page bg-body-secondary">

<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <span class="h1 fw-bold">Lintune</span>
    </div>
    <div class="card-body">
      <p class="login-box-msg">Sign in to your account</p>

      @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login.submit') }}">
        @csrf
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email address"
                 value="{{ old('email') }}" required autocomplete="email" />
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        </div>
        <button type="submit" class="btn btn-primary w-100">Continue</button>
      </form>
    </div>
  </div>
</div>

</body>
</html>
