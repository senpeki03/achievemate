@extends('dean.deansidebar')

@section('content')
<div class="container py-4">

  @php
    // Level hierarchy for Dean side:
    // - "program"  => list of programs under Dean's college
    // - "major"    => list of majors under a selected program
    $level      = $level ?? 'program';      // default: list programs
    $collegeId  = $collegeId ?? null;
    $programId  = $programId ?? null;

    // Get Dean's college from UserDesignation if not explicitly passed
    $deanUD = auth()->user()->userDesignation ?? null;
    $deanCollege = $deanUD?->college;
    $userCollegeName = $collegeName
        ?? ($deanCollege->College_name ?? 'Your College');

    $backHref = null;
    $backText = 'Back';

    // Back button for Dean:
    // - from major → back to program list
    if ($level === 'major') {
      $backHref = route('dean.reviewedgraduationlist', [
        'level'      => 'program',
        'college_id' => $collegeId,
      ]);
      $backText = 'Back to Programs';
    }

    $rows    = collect($rows ?? []);
    $hasRows = $rows->sum('total') > 0;
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
      @if($backHref)
        <a href="{{ $backHref }}" class="btn btn-outline-light rounded-3">
          <i class="bi bi-arrow-left-circle me-1"></i> {{ $backText }}
        </a>
      @endif

      <div class="d-flex flex-column">
        <h3 class="fw-bold text-white mb-1">{{ $title ?? "Graduation List (Reviewed)" }}</h3>
        <span class="text-light small">
          College: <strong>{{ $userCollegeName }}</strong>
        </span>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      @if(!$hasRows)
        <div class="d-flex align-items-center justify-content-center" style="height:70vh;">
          <div class="text-center">
            <div class="mx-auto mb-4">
              <img src="{{ asset('img/box2.png') }}" alt="Empty Box"
                   style="width:110px;height:auto;opacity:0.9;">
            </div>
            <h5 class="fw-semibold mb-2">No Graduating Students</h5>
            <p class="text-muted mb-0">
              There are currently no Fourth-Year students with graduation forms and
              requirements for this
              {{ $level === 'program' ? 'program list' : 'major' }}.
            </p>
          </div>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:64px">#</th>
                <th>
                  @if($level === 'program')
                    Program
                  @else
                    Major
                  @endif
                </th>
                <th class="text-end">
                  No. of Graduating Students (Fourth Year)
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $i => $row)
                @php
                  if ($level === 'program') {
                    // Program → go to majors under this program
                    $href = route('dean.reviewedgraduationlist', [
                      'level'      => 'major',
                      'college_id' => $collegeId ?? ($deanCollege->College_id ?? null),
                      'program_id' => $row->id,
                    ]);
                  } else {
                    // Major → detailed list of students
                    $href = route('dean.reviewedgraduationlist.students', [
                      'college_id' => $collegeId ?? ($deanCollege->College_id ?? null),
                      'program_id' => $programId,
                      'major_id'   => $row->id ?? 0, // 0 = no major
                    ]);
                  }
                @endphp
                <tr class="clickable-row" data-href="{{ $href }}" style="cursor:pointer;">
                  <td>{{ $i + 1 }}</td>
                  <td class="fw-semibold">{{ $row->name }}</td>
                  <td class="text-end">{{ number_format((int) $row->total) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', () => {
      const href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
</script>
@endsection
