{{-- resources/views/student/portfolio.blade.php --}}
@extends('student.studentsidebar')

@section('content')
@php use Illuminate\Support\Str; @endphp
<style>
  .card-soft { border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }

  .avatar {
    width:110px; height:110px; border-radius:12px;
    background:#e9ecef; display:flex; align-items:center; justify-content:center;
  }

  /* Header typography tweaks */
  .header-name   { font-size:1.35rem; line-height:1.2; margin-top:-4px; }   /* ↑ a bit larger & nudged up */
  .header-prog   { font-size:.95rem;  line-height:1.2; letter-spacing:.2px; }
  .header-year   { font-size:.95rem;  line-height:1.2; letter-spacing:.2px; margin-top:2px; }

  .badge-pill { border-radius:999px; padding:.4rem .75rem; font-weight:600; }
  .thumb { aspect-ratio: 4/3; width:100%; object-fit:cover; border-radius:10px; }
  .list-dot::before { content:'• '; color:#6c757d; }
</style>

<div class="container py-3">

  {{-- 1) Header --}}
  <div class="card card-soft p-3 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="avatar">
        <i class="bi bi-person fs-1 text-secondary"></i>
      </div>
      <div class="d-flex flex-column">
        {{-- NAME (UPPERCASE, slightly larger, moved up) --}}
        <div class="fw-bold header-name">{{ Str::upper($studentName ?? 'Student') }}</div>
        {{-- PROGRAM (UPPERCASE) --}}
        <div class="text-muted header-prog">
          {{ Str::upper($studentProgram ?? '—') }}
        </div>
        {{-- YEAR LEVEL (UPPERCASE) ON ITS OWN LINE --}}
        <div class="text-muted header-year">
          YEAR {{ Str::upper($studentYearLevel ?? '—') }}
        </div>
      </div>
    </div>
  </div>

  {{-- 2) List of Badges --}}
  <div class="card card-soft p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0 fw-bold">List of Badges</h5>
      @if(!empty($badges) && count($badges))
        <span class="badge bg-light text-secondary">{{ count($badges) }}</span>
      @endif
    </div>
    @if(!empty($badges) && count($badges))
      <div class="d-flex flex-wrap gap-3">
        @foreach($badges as $b)
          {{-- $b['icon'] is already a full /storage/... or http(s) URL --}}
          <img src="{{ $b['icon'] }}" alt="badge" style="height:40px">
        @endforeach
      </div>
    @else
      <div class="text-muted">No badges yet.</div>
    @endif
  </div>

  <div class="row g-3">
    {{-- 3) Certificate gallery --}}
    <div class="col-lg-6">
      <div class="card card-soft p-3 h-100">
        <h5 class="fw-bold mb-3">Certificates</h5>

        @if(!empty($deansCertificates) && count($deansCertificates))
          <div class="row g-3">
            @foreach($deansCertificates as $c)
              @php
                $sem  = trim($c['semester'] ?? '');
                $sy   = trim($c['school_year'] ?? '');
                $subtitle = $sem . ($sem && $sy ? ', ' : '') . ($sy ? 'AY '.$sy : '');
              @endphp
              <div class="col-6">
                <a href="{{ $c['preview_url'] }}" target="_blank" class="text-decoration-none">
                  @if(!empty($c['is_image']) && !empty($c['thumbnail']))
                    <img class="thumb" src="{{ $c['thumbnail'] }}" alt="certificate">
                  @else
                    <div class="thumb d-flex align-items-center justify-content-center bg-light">
                      <i class="bi bi-filetype-pdf fs-1 text-secondary"></i>
                    </div>
                  @endif
                  <div class="mt-2 small fw-semibold text-dark">{{ $c['title'] }}</div>
                  <div class="small text-muted">{{ $subtitle }}</div>
                </a>
              </div>
            @endforeach
          </div>
        @else
          <div class="text-muted">No certificates yet.</div>
        @endif
      </div>
    </div>

    {{-- 4) List of Achievements --}}
    <div class="col-lg-6">
      <div class="card card-soft p-3 h-100">
        <h5 class="fw-bold mb-3">List of Achievements</h5>

        @if(!empty($achievements) && count($achievements))
          <div class="list-group">
            @foreach($achievements as $a)
              <div class="list-group-item d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-semibold">{{ $a['title'] }}</div>
                  <div class="small text-muted">
                    {{ $a['semester'] ?? '' }}{{ !empty($a['school_year']) ? ', AY '.$a['school_year'] : '' }}
                    @if(!empty($a['note'])) <span class="list-dot">{{ $a['note'] }}</span> @endif
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  @if(!empty($a['icon']))
                    <img src="{{ $a['icon'] }}" alt="" style="height:22px">
                  @else
                    <i class="bi bi-award text-secondary"></i>
                  @endif
                  @if(!empty($a['action_url']))
                    <a href="{{ $a['action_url'] }}" class="btn btn-sm btn-outline-primary">View</a>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="text-muted">No achievements yet.</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Optional: list view with buttons --}}
  @if(!empty($deansCertificates) && count($deansCertificates))
    <h5 class="mt-4 fw-bold">Dean’s Lister Certificates</h5>
    <ul class="list-group mb-4">
      @foreach($deansCertificates as $c)
        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="d-flex flex-column">
            <strong>{{ $c['title'] }}</strong>
            <small class="text-muted">
              {{ $c['semester'] ?? '' }}{{ !empty($c['school_year']) ? ', AY '.$c['school_year'] : '' }}
              @if(!empty($c['gwa'])) • GWA: <span class="fw-semibold">{{ $c['gwa'] }}</span>@endif
              @if(!empty($c['rank'])) • Rank: <span class="fw-semibold">{{ $c['rank'] }}</span>@endif
            </small>
          </div>
          <span class="d-flex gap-2 ms-auto">
            <a href="{{ $c['preview_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank">Preview</a>
            <a href="{{ $c['download_url'] }}" class="btn btn-sm btn-primary">Download</a>
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
