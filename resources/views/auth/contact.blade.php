<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lintune – Not Authorized</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc2/dist/css/adminlte.min.css" />
</head>
<body class="login-page bg-body-secondary">

<div class="login-box">
  <div class="card card-outline card-danger">
    <div class="card-header text-center">
      <span class="h1 fw-bold">Lintune</span>
    </div>
    <div class="card-body text-center">
      <p class="mb-3">Your account is not registered or not authorized to access this application.</p>
      <p class="text-muted">Please contact your administrator to request access.</p>
      <a href="{{ route('login') }}" class="btn btn-secondary mt-3">Back to login</a>
    </div>
  </div>
</div>

</body>
</html>
