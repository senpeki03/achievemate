@extends('student.studentsidebar', ['unreadCount' => $unreadCount])

@section('content')
<div class="container py-4">

  {{-- ===== Messages ===== --}}
  <h4 class="fw-bold mb-4">Messages</h4>

  @forelse($posts->reverse() as $post) {{-- newest first --}}
    @php $rec = $post->recipients->first(); @endphp
    <div class="card mb-2 border-0 shadow-sm bg-light {{ $rec && !$rec->is_read ? 'unread' : 'read' }}" style="border-radius: 12px;">
      <div class="card-body d-flex flex-column py-2 px-3" style="font-size: 0.9rem;">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-envelope-fill" style="color:#6c5ce7;"></i>
          <h6 class="mb-0 fw-bold" style="color:#2d3436;font-size:1rem;">{{ $post->Title }}</h6>
        </div>
        <p class="mb-1">{{ $post->Announcement }}</p>
        <small class="text-muted">{{ \Carbon\Carbon::parse($post->Start_date)->format('F d, Y h:i A') }}</small>

        @if($rec && !$rec->is_read)
          <button class="btn btn-sm btn-primary mt-2 mark-as-read" data-id="{{ $post->Post_id }}">
            Mark as Read
          </button>
        @endif
      </div>
    </div>
  @empty
    <p class="text-muted">You have no notifications yet.</p>
  @endforelse

  <hr class="my-4">

{{-- ===== Awards & Claims ===== --}}
<h4 class="fw-bold mb-3">Awards & Claims</h4>

@php
  /** @var \Illuminate\Support\Collection $notifications */
  $awardList = isset($notifications) ? $notifications : collect();
@endphp

@forelse($awardList as $n)
  @php
    // Normalize flags
    $isClaimed   = (!$n->claimable) || !empty($n->claimed_at);
    $canClaim    = $n->claimable && empty($n->claimed_at) && !empty($n->claim_token);

    // Pull certificate URL (if already set in data)
    $certRel  = data_get($n->data, 'certificate_path');
    $certUrl  = $certRel ? asset('storage/' . ltrim($certRel, '/')) : null;

    // Optional badge (not required to show)
    $badgeRel = data_get($n->data, 'badge_path');
    $badgeUrl = $badgeRel ? asset('storage/' . ltrim($badgeRel, '/')) : null;
  @endphp

  <div class="card mb-2 p-3 border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <div class="fw-bold">{{ $n->title ?? 'Award' }}</div>
        <div class="text-muted small">{{ $n->message ?? '' }}</div>
        @if(!empty($n->created_at))
          <div class="text-muted small mt-1">
            {{ \Carbon\Carbon::parse($n->created_at)->format('F d, Y h:i A') }}
          </div>
        @endif
      </div>

      <div class="d-flex align-items-center gap-2">
        @if($canClaim)
          {{-- ✅ FIX: correct route name is student.awards.claim (plural) --}}
          <a class="btn btn-sm btn-primary"
             href="{{ route('student.award.claim', ['token' => $n->claim_token]) }}">
            Claim
          </a>
        @else
          @if($certUrl)
            <a class="btn btn-sm btn-outline-primary" href="{{ $certUrl }}" target="_blank" rel="noopener">
              View Certificate
            </a>
          @else
            <button class="btn btn-sm btn-secondary" disabled>Claimed</button>
          @endif
        @endif
      </div>
    </div>
  </div>
@empty
  <p class="text-muted">No award notifications yet.</p>
@endforelse


</div>



{{-- Claim success modal --}}
@if(session('claim_success'))
  @php
    $flash = session('claim_success');
    $title = $flash['title'] ?? 'Congratulations!';
    $msg   = $flash['message'] ?? 'Keep up the good work! Your certificate has been added to your Portfolio.';
    $cert  = session('claimed_cert_url'); // optional "View Certificate" button
  @endphp

  <div class="modal fade" id="claimCongrats" tabindex="-1" aria-labelledby="claimCongratsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold" id="claimCongratsLabel">{{ $title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body pt-0">
          <p class="mb-0">{{ $msg }}</p>
        </div>

        <div class="modal-footer border-0 d-flex justify-content-between">
          <a class="btn btn-primary" href="{{ route('student.portfolio') }}">
            Go to Portfolio
          </a>

          @if($cert)
            <a class="btn btn-outline-primary" href="{{ $cert }}" target="_blank" rel="noopener">
              View Certificate
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>


<script>
  // Mark-as-read (messages)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.mark-as-read');
    if(!btn) return;

    const id = btn.getAttribute('data-id');
    fetch(@json(route('student.notifications.markAsRead')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': @json(csrf_token())
      },
      body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(res => {
      const card = btn.closest('.card');
      if(card){ card.classList.remove('unread'); card.classList.add('read'); }
      btn.style.display = 'none';
      if(res && typeof res.unreadCount !== 'undefined'){
        const badge = document.getElementById('unreadCount');
        if (badge) badge.textContent = res.unreadCount;
      }
    })
    .catch(() => {});
  });

    // Mark-as-read (messages) — existing code…
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.mark-as-read');
    if(!btn) return;

    const id = btn.getAttribute('data-id');
    fetch(@json(route('student.notifications.markAsRead')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': @json(csrf_token())
      },
      body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(res => {
      const card = btn.closest('.card');
      if(card){ card.classList.remove('unread'); card.classList.add('read'); }
      btn.style.display = 'none';
      if(res && typeof res.unreadCount !== 'undefined'){
        const badge = document.getElementById('unreadCount');
        if (badge) badge.textContent = res.unreadCount;
      }
    })
    .catch(() => {});
  });

  // Show the claim-success modal if present
  document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('claimCongrats');
    if (el && window.bootstrap && bootstrap.Modal) {
      new bootstrap.Modal(el).show();
    }
  });
</script>

@endsection