{{-- resources/views/student/event-invite.blade.php --}}
@extends('student.studentsidebar')
@section('title', 'Event Invites')

@section('content')
<div class="container py-4">
  {{-- Main Outer Card --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      {{-- Page Title --}}
      <h3 class="fw-bold mb-2">Event Invitations</h3>
      <p class="text-muted mb-4">
        Below are the events you have been invited to.
      </p>

      {{-- Flash Messages --}}
      @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger mb-3">{{ session('error') }}</div>
      @endif

      @if($invites->isEmpty())
        <div class="alert alert-info">
          You currently have no event invitations.
        </div>
      @else
        <div class="row">
          @foreach($invites as $invite)
            @php $event = $invite->event; @endphp

            {{-- Nested Event Card --}}
            <div class="col-md-4 mb-4">
              <div class="card border"
                   style="border-left: 5px solid
                   @if($invite->status === 'Accepted') #28a745
                   @elseif($invite->status === 'Declined') #dc3545
                   @elseif($invite->status === 'Cancelled') #6c757d
                   @else #ffc107
                   @endif;">
                <div class="card-body d-flex flex-column">

                  {{-- Event Header --}}
                  <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                      <h5 class="fw-bold mb-1">{{ $event->title }}</h5>

                      {{-- Show type_name here --}}
                      @if($event && $event->eventType)
                        <p class="mb-1 small text-primary">
                          <strong>Type:</strong> {{ $event->eventType->type_name }}
                        </p>
                      @endif

                      @if(!empty($event->description))
                        <p class="text-muted small mb-2">{{ $event->description }}</p>
                      @endif
                    </div>

                    {{-- Status Badge --}}
                    <span class="badge
                      @if($invite->status === 'Accepted') bg-success
                      @elseif($invite->status === 'Declined') bg-danger
                      @elseif($invite->status === 'Cancelled') bg-secondary
                      @else bg-warning text-dark
                      @endif"
                      style="font-size: 13px; padding:6px 10px;">
                      {{ $invite->status }}
                    </span>
                  </div>

                  <hr class="my-2">

                  {{-- Event Info --}}
                  <p class="mb-1"><strong>Date:</strong>
                    {{ \Carbon\Carbon::parse($event->start_at)->format('F d, Y') }}
                  </p>

                  <p class="mb-1"><strong>Time:</strong>
                    {{ \Carbon\Carbon::parse($event->start_at)->format('h:i A') }}
                    –
                    {{ \Carbon\Carbon::parse($event->end_at)->format('h:i A') }}
                  </p>

                  @if(!empty($event->venue))
                    <p class="mb-2"><strong>Venue:</strong> {{ $event->venue }}</p>
                  @endif

                  {{-- Spacer to push buttons to bottom --}}
                  <div class="flex-grow-1"></div>

                  {{-- Action Buttons --}}
                  @if(!in_array($invite->status, ['Accepted', 'Declined', 'Cancelled']))
                    <div class="d-flex gap-2 mt-2">

                      {{-- Accept --}}
                      <form method="POST"
                            action="{{ route('student.event-update', $invite->Assignment_id) }}"
                            class="flex-fill">
                        @csrf
                        <input type="hidden" name="status" value="Accepted">
                        <button type="submit" class="btn btn-success w-100 btn-sm">
                          <i class="bi bi-check-circle me-1"></i> Accept
                        </button>
                      </form>

                      {{-- Decline --}}
                      <form method="POST"
                            action="{{ route('student.event-update', $invite->Assignment_id) }}"
                            class="flex-fill">
                        @csrf
                        <input type="hidden" name="status" value="Declined">
                        <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                          <i class="bi bi-x-circle me-1"></i> Decline
                        </button>
                      </form>

                    </div>
                  @else
                    <p class="text-muted small mt-2 mb-0">
                      @if($invite->status === 'Accepted')
                        You have accepted this invitation.
                      @elseif($invite->status === 'Declined')
                        You have declined this invitation.
                      @else
                        This invitation was cancelled.
                      @endif
                    </p>
                  @endif

                </div>
              </div>
            </div>

          @endforeach
        </div>

      @endif

    </div>
  </div>
</div>
@endsection
