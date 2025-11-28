{{-- resources/views/student/portfolio.blade.php --}}
@extends('student.studentsidebar')

@section('content')
@php use Illuminate\Support\Str; @endphp

<style>
  /* ===== HERO HEADER (BG IMAGE + GLASS EFFECT + DIAGONAL) ===== */

  .student-hero-wrapper {
    position: relative;
    width: 100%;
    min-height: 180px;
    border-radius: 18px;
    overflow: hidden;
    background: #7A0000 url('{{ asset("img/bg.jpg") }}') no-repeat center center;
    background-size: cover;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    border: 1px solid #e8e8e8;
    margin-bottom: 1.5rem;
  }

  /* Semi-transparent white layer para hindi lumubog text sa image */
  .student-hero-tint {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.78);
    z-index: 1;
  }

  /* Red + yellow diagonal at the bottom */
  .student-hero-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 90px;
    background: linear-gradient(to right, #c8102e 0%, #c8102e 55%, #f9b233 55%, #f9b233 100%);
    clip-path: polygon(0 0, 100% 90%, 100% 100%, 0 100%);
    z-index: 2;
  }

  .student-hero-content {
    position: relative;
    z-index: 3;
    padding: 1.5rem 2rem;
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    color: #111;
  }

  .student-photo-wrap {
    width: 120px;
    height: 140px;
    border-radius: 4px;
    border: 3px solid #fff;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 3px 8px rgba(0,0,0,.20);
  }

  .student-photo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .student-icons {
    display: flex;
    gap: .4rem;
    margin-top: .4rem;
  }

  .student-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: none;
    background: #f1f1f1;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    color:#555;
    transition: .2s;
  }

  .student-icon-btn:hover {
    background:#e1e1e1;
  }

  .student-info h4 {
    margin: 0;
    font-weight: 700;
    font-size: 1.25rem;
    letter-spacing: .03em;
    color:#111;
  }

  .info-line {
    color:#222;
    font-size: .92rem;
    margin-top: 2px;
  }

  .info-line i {
    font-size: .6rem;
    margin-right: 4px;
    color:#222;
  }

  .badge-enrolled {
    display: inline-block;
    padding: .28rem .75rem;
    background: #16a34a;
    color: white;
    border-radius: 999px;
    font-size: .75rem;
    margin-top: .5rem;
    font-weight: 600;
  }

  /* ===== OTHER CARDS ===== */
  .card-soft { border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
  .thumb { aspect-ratio: 4/3; width:100%; object-fit:cover; border-radius:10px; }
  .badge-pill { border-radius:999px; padding:.4rem .75rem; font-weight:600; }
  .list-dot::before { content:'• '; color:#6c757d; }
</style>

<div class="container py-3">

  {{-- =======================
       STUDENT HEADER HERO
     ======================= --}}
  <div class="student-hero-wrapper">
    <div class="student-hero-tint"></div>
    <div class="student-hero-overlay"></div>

    <div class="student-hero-content">

      {{-- PHOTO + ICONS --}}
      <div>
        <div class="student-photo-wrap">
          <img src="{{ $photoUrl }}" alt="Student photo">
        </div>

        <div class="student-icons">
          <button type="button" class="student-icon-btn" title="Email"><i class="bi bi-envelope"></i></button>
          <button type="button" class="student-icon-btn" title="Edit profile"><i class="bi bi-pencil"></i></button>
        </div>
      </div>

      {{-- INFO --}}
      <div class="student-info flex-grow-1">

        {{-- NAME --}}
        <h4>{{ $studentName }}</h4>

        {{-- FIRST SEMESTER AY ... --}}
        <div class="info-line">
          <i class="bi bi-caret-right-fill"></i>
          {{ $semesterLabel }}
        </div>

        {{-- COLLEGE + CAMPUS --}}
        <div class="info-line">
          <i class="bi bi-caret-right-fill"></i>
          {{ $course?->college?->College_name ?? 'College not set' }}
          @if($course?->campus)
            - {{ $course->campus->Campus_name }}
          @endif
        </div>

        {{-- PROGRAM + YEAR LEVEL --}}
        <div class="info-line">
          <i class="bi bi-caret-right-fill"></i>
          <strong>{{ $course?->program?->Program_name ?? $studentProgram }}</strong>
          – {{ Str::upper($studentYearLevel) }}
        </div>

        {{-- MAJOR (optional) --}}
        @if($course?->major)
          <div class="info-line">
            <i class="bi bi-caret-right-fill"></i>
            {{ $course->major->Major_name }}
          </div>
        @endif

        <span class="badge-enrolled">{{ $enrollmentStatus }}</span>

      </div>

    </div>
  </div>

  {{-- =======================
       LIST OF BADGES
     ======================= --}}
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
          <img src="{{ $b['img'] ?? asset('img/cert/badge_silver.png') }}" 
               alt="{{ $b['label'] ?? 'Badge' }}" 
               style="height:40px">
        @endforeach
      </div>
    @else
      <div class="text-muted">No badges yet.</div>
    @endif
  </div>

  <div class="row g-3">
    {{-- =======================
         Certificate gallery
       ======================= --}}
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

    {{-- =======================
         List of Achievements
       ======================= --}}
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

  {{-- =======================
       List view of certificates
     ======================= --}}
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
