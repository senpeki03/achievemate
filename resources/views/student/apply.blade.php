@extends('student.studentsidebar')
@section('title','Application for Graduation')

@section('content')
<div class="container py-4">
  <h3 class="mb-3">Application for Graduation</h3>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>   @endif

  <form method="POST" action="{{ route('student.graduation.store') }}" class="card p-3 mb-3">
    @csrf
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Application No.</label>
        <input class="form-control" value="{{ $app->application_no }}" disabled>
      </div>
      <div class="col-md-4">
        <label class="form-label">College</label>
        <input name="college_id" class="form-control" placeholder="College ID / picklist"
               value="{{ old('college_id', $app->college_id) }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Program</label>
        <input name="program_id" class="form-control" placeholder="Program ID / picklist"
               value="{{ old('program_id', $app->program_id) }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Term End (optional)</label>
        <input type="date" name="term_end" class="form-control"
               value="{{ old('term_end', optional($app->term_end)->format('Y-m-d')) }}">
      </div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary">Save</button>
      <a href="{{ route('student.graduation.show') }}" class="btn btn-outline-secondary">Go to Checklist</a>
    </div>
  </form>

  <div class="alert alert-info small">
    Upload the required items below. When finished, click <em>Submit Application</em>.
  </div>

  {{-- Update include path if you relocated the partial --}}
  @include('student.requirements_table', ['app' => $app])
</div>
@endsection
