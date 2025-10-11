@extends('programchair.programchairsidebar')

@section('content')
<div class="container py-4">

  <!-- 🔷 Title Bar -->
  <div class="mb-4 p-4 shadow-sm" style="background-color: #ffffff; border: 1px solid black; border-radius: 12px; height: 300px; overflow: hidden;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="fw-bold mb-1 chart-title">Enrolled Students by Department</h4>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="toggleChart" {{ session('dark_mode') ? 'checked' : '' }}>
        <label class="form-check-label toggle-label" for="toggleChart">Toggle View</label>
      </div>
    </div>

@endsection
