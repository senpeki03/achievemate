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

  @php $awardList = isset($notifications) ? $notifications : collect(); @endphp

  @forelse($awardList as $n)
    <div class="card mb-2 p-3 border-0 shadow-sm">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-bold">{{ $n->title ?? 'Award' }}</div>
          <div class="text-muted small">{{ $n->message ?? '' }}</div>
          @if(!empty($n->created_at))
            <div class="text-muted small mt-1">
              {{ \Carbon\Carbon::parse($n->created_at)->format('F d, Y h:i A') }}
            </div>
          @endif
        </div>

        @php
          $isClaimed = (!$n->claimable) || !empty($n->claimed_at);
        @endphp

        @if(!$isClaimed && $n->claim_token)
          <a class="btn btn-sm btn-primary" href="{{ route('student.award.claim', $n->claim_token) }}">
            Claim
          </a>
        @else
          <button class="btn btn-sm btn-secondary" disabled>
            Claimed
          </button>
        @endif
      </div>
    </div>
  @empty
    <p class="text-muted">No award notifications yet.</p>
  @endforelse

</div>
@endsection

@push('scripts')
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
</script>
@endpush
