@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@if ($stats === null)
  <div class="alert alert-warning">Could not load dashboard stats.</div>
@else
  <div class="row">
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-info">
        <div class="inner"><h3>{{ $stats['total_users'] }}</h3><p>Total Users</p></div>
        <i class="small-box-icon bi bi-people"></i>
      </div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-success">
        <div class="inner"><h3>{{ $stats['active_users'] }}</h3><p>Active Users</p></div>
        <i class="small-box-icon bi bi-person-check"></i>
      </div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-warning">
        <div class="inner"><h3>{{ $stats['disabled_users'] }}</h3><p>Disabled Users</p></div>
        <i class="small-box-icon bi bi-person-x"></i>
      </div>
    </div>
  </div>
@endif
@endsection
