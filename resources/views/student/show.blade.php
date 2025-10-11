@extends('student.studentsidebar')
@section('title','My Graduation Application')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">My Graduation Application</h3>
    <a class="btn btn-outline-secondary" href="{{ route('student.grad.apply') }}">Edit Profile/Term</a>
  </div>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>   @endif

  <div class="mb-2">
    <span class="badge bg-primary">Status: {{ Str::headline($app->status) }}</span>
  </div>

  @include('student.grad._requirements_table', ['app'=>$app])
</div>
@endsection
