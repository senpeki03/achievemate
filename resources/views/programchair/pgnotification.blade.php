@extends('programchair.programchairsidebar')

@section('content')
<div class="container py-4">

  {{-- ===== Application Notifications for Program Chair ===== --}}
  <h4 class="fw-bold mb-4">Application Notifications</h4>

  @forelse($notifications as $rec)
    @php
      /** @var \App\Models\ApplicationRecipient $rec */
      $app     = $rec->application;
      $student = $app ? $app->student : null;

      // Using StudentManage fields
      $studentId   = $student->Student_id ?? 'N/A';
      $studentCode = $student->SRCODE ?? null;
      $studentName = $student?->full_name ?? 'UNKNOWN STUDENT';
      $gwa         = $app->GWA ?? 'N/A';
    @endphp

    <div class="card mb-2 border-0 shadow-sm bg-light {{ !$rec->is_read ? 'unread' : 'read' }}"
         style="border-radius: 12px;">
      <div class="card-body d-flex flex-column py-2 px-3" style="font-size: 0.9rem;">

        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-bell-fill" style="color:#6c5ce7;"></i>
          <h6 class="mb-0 fw-bold" style="color:#2d3436;font-size:1rem;">
            New Application Submitted
          </h6>
        </div>

        <p class="mb-1">
          <strong>Student ID:</strong> {{ $studentId }} <br>
          @if($studentCode)
            <strong>SR-CODE:</strong> {{ $studentCode }} <br>
          @endif
          <strong>Student Name:</strong> {{ $studentName }} <br>
          <strong>GWA:</strong>
            {{ is_numeric($gwa) ? number_format((float) $gwa, 2) : $gwa }}
        </p>

        @if($app)
          <small class="text-muted d-block mt-1">
            Application ID: {{ $app->Application_id }}
            @if(!empty($app->Status))
              &middot; Status: {{ $app->Status }}
            @endif
          </small>
        @endif

        @if(!$rec->is_read)
          <button class="btn btn-sm btn-primary mt-2 mark-as-read"
                  data-id="{{ $rec->application_recipientt_id }}">
            Mark as Read
          </button>
        @endif
      </div>
    </div>
  @empty
    <p class="text-muted">No application notifications yet.</p>
  @endforelse

</div>

{{-- Mark-as-read (AJAX) --}}
<script>
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.mark-as-read');
    if(!btn) return;

    const id = btn.getAttribute('data-id');

    fetch(@json(route('programchair.notifications.markAsRead')), {
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
      if(card){
        card.classList.remove('unread');
        card.classList.add('read');
      }
      btn.style.display = 'none';

      if(res && typeof res.unreadCount !== 'undefined'){
        const badge = document.getElementById('unreadCount');
        if (badge) badge.textContent = res.unreadCount;
      }
    })
    .catch(() => {});
  });
</script>
@endsection
