{{-- resources/views/programchair/events.blade.php --}}
@extends('programchair.programchairsidebar')

@section('title', 'Create Events')

@section('content')

<style>
    :root {
        --brand:#0C2340;
        --muted:#6b7280;
        --line:#e5e7eb;
    }

    .events-wrapper {
        margin: 20px 25px;
    }

    .events-container {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .events-header {
        font-size: 20px;
        font-weight: 700;
        color: var(--brand);
        margin-bottom: 4px;
    }

    .sub-header {
        font-size: 14px;
        color: var(--muted);
        margin-bottom: 20px;
    }

    table.events-table {
        width: 100%;
        font-size: 13px;
        border-collapse: collapse;
    }

    table.events-table th,
    table.events-table td {
        padding: 10px;
        border: 1px solid var(--line);
        vertical-align: middle;
    }

    table.events-table th {
        background: #f8fafc;
        color: var(--brand);
        font-weight: 700;
        text-align: center;
    }

    .btn-create {
        background: var(--brand);
        color: #fff;
    }

    table.invites-table,
    table.event-types-table {
        width: 100%;
        font-size: 13px;
        border-collapse: collapse;
    }

    table.invites-table th,
    table.invites-table td,
    table.event-types-table th,
    table.event-types-table td {
        padding: 8px 10px;
        border: 1px solid var(--line);
        vertical-align: middle;
    }

    table.invites-table th,
    table.event-types-table th {
        background: #f8fafc;
        color: var(--brand);
        font-weight: 700;
        text-align: center;
    }
</style>

@php
    // Map invited students per event for invite modal
    $invitedByEvent = [];
    foreach ($events as $ev) {
        $invitedByEvent[$ev->Event_id] = $ev->assignments
            ->where('status', '!=', 'Cancelled')
            ->pluck('Student_id')
            ->values()
            ->all();
    }
@endphp

<div class="events-wrapper">

    <div class="events-container">
        {{-- Header + buttons INSIDE card --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="events-header">College Events</div>
                <div class="sub-header">List of all events created by you</div>
            </div>

            <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#eventTypesModal">
                    <i class="bi bi-list-ul me-1"></i> Event Types
                </button>

                <button type="button"
                        class="btn btn-create"
                        data-bs-toggle="modal"
                        data-bs-target="#createEventModal">
                    <i class="bi bi-plus-circle me-1"></i> Create New Event
                </button>
            </div>
        </div>


        <table class="events-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th style="width:150px;">Type</th>
                    <th style="width:160px;">Start</th>
                    <th style="width:160px;">End</th>
                    <th style="width:120px;">Slots</th>
                    <th style="width:140px;">Assigned</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>

              <tbody>
              @forelse ($events as $event)
                  @php
                      $slots          = (int) ($event->number_of_students ?? 0);
                      $acceptedCount  = $event->assignments->where('status', 'Accepted')->count();
                      $isFull         = $slots > 0 && $acceptedCount >= $slots;
                  @endphp

                  <tr>
                      <td>{{ $event->title }}</td>

                      <td class="text-center">
                          {{ optional($event->eventType)->type_name ?? '—' }}
                      </td>

                      <td class="text-center">
                          {{ \Carbon\Carbon::parse($event->start_at)->format('M d, Y h:i A') }}
                      </td>

                      <td class="text-center">
                          {{ \Carbon\Carbon::parse($event->end_at)->format('M d, Y h:i A') }}
                      </td>

                      <td class="text-center">
                          {{ $slots > 0 ? $slots : '—' }}
                      </td>

                      <td class="text-center">
                          {{ $event->assigned_count ?? 0 }}
                          @if($slots > 0)
                              <span class="text-muted small d-block">
                                  Accepted: {{ $acceptedCount }}/{{ $slots }}
                              </span>
                          @endif
                      </td>

                      <td class="text-center">{{ $event->status }}</td>

                      <td class="text-center">
                          {{-- ICON ROW ACTIONS --}}
                          <div class="d-flex justify-content-center align-items-center gap-1">
                              {{-- View invited --}}
                              <button type="button"
                                      class="btn btn-sm btn-outline-danger"
                                      data-bs-toggle="modal"
                                      data-bs-target="#viewInvitesModal-{{ $event->Event_id }}"
                                      title="View Invited Students">
                                  <i class="bi bi-eye"></i>
                              </button>

                              {{-- 3-Dot Actions Dropdown --}}
                              <div class="dropdown">
                                  <button class="btn btn-sm btn-outline-danger dropdown-toggle" type="button" id="dropdownMenuButton-{{ $event->Event_id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                      <i class="bi bi-three-dots-vertical"></i>
                                  </button>
                                  <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton-{{ $event->Event_id }}">
                                      {{-- Edit Event --}}
                                      <li><button class="dropdown-item btn-edit-event"
                                                  data-bs-toggle="modal"
                                                  data-bs-target="#editEventModal"
                                                  data-id="{{ $event->Event_id }}"
                                                  data-type-id="{{ $event->EventType_id }}"
                                                  data-title="{{ $event->title }}"
                                                  data-description="{{ $event->description }}"
                                                  data-start="{{ \Carbon\Carbon::parse($event->start_at)->format('Y-m-d\TH:i') }}"
                                                  data-end="{{ \Carbon\Carbon::parse($event->end_at)->format('Y-m-d\TH:i') }}"
                                                  data-slots="{{ $event->number_of_students }}"
                                                  title="Edit Event">
                                                  <i class="bi bi-pencil-square"></i> Edit Event
                                      </button></li>

                                      {{-- Delete Event --}}
                                      <li>
                                          <form method="POST"
                                                action="{{ route('programchair.events.destroy', $event->Event_id) }}"
                                                data-confirm-title="Delete Event"
                                                data-confirm-message="Delete this event? This action cannot be undone."
                                                class="d-inline">
                                              @csrf
                                              @method('DELETE')
                                              <button type="submit"
                                                      class="dropdown-item">
                                                  <i class="bi bi-trash"></i> Delete Event
                                              </button>
                                          </form>
                                      </li>
                                  </ul>
                              </div>
                          </div>

                          @if($isFull)
                              <small class="text-muted d-block mt-1">
                                  ✅ Complete participants already ({{ $acceptedCount }}/{{ $slots }})
                              </small>
                          @endif
                      </td>
                  </tr>

              @empty
                  <tr>
                      <td colspan="8" class="text-center text-muted py-3">
                          No events created yet.
                      </td>
                  </tr>
              @endforelse
              </tbody>

        </table>
    </div>
</div>

{{-- =========================
     EVENT TYPES MODAL
========================= --}}
<div class="modal fade" id="eventTypesModal" tabindex="-1" aria-labelledby="eventTypesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="eventTypesLabel">
                    Event Types
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                {{-- Add new event type (with confirmation) --}}
                <form method="POST"
                      action="{{ route('programchair.event-types.store') }}"
                      class="mb-3 d-flex gap-2 align-items-start"
                      data-confirm-title="Add Event Type"
                      data-confirm-message="Add this new event type?">
                    @csrf
                    <div class="flex-grow-1">
                        <input type="text"
                               name="type_name"
                               class="form-control @error('type_name') is-invalid @enderror"
                               placeholder="Enter new event type (e.g., Orientation, Seminar)"
                               value="{{ old('type_name') }}"
                               required>
                        @error('type_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-plus-circle me-1"></i> Add
                    </button>
                </form>

                <hr>

                @if(isset($eventTypes) && $eventTypes->isNotEmpty())
                    <div class="table-responsive">
                        <table class="event-types-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Type Name</th>
                                    <th style="width:140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($eventTypes as $idx => $type)
                                    <tr>
                                        <td class="text-center">{{ $idx + 1 }}</td>
                                        <td>{{ $type->type_name }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                {{-- Edit type --}}
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger btn-edit-event-type"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editEventTypeModal"
                                                        data-id="{{ $type->EventType_id }}"
                                                        data-name="{{ $type->type_name }}"
                                                        title="Edit Event Type">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>

                                                {{-- Delete type --}}
                                                <form method="POST"
                                                      action="{{ route('programchair.event-types.destroy', $type->EventType_id) }}"
                                                      data-confirm-title="Delete Event Type"
                                                      data-confirm-message="Delete this event type? This cannot be undone."
                                                      class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete Event Type">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No event types defined yet.</p>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

{{-- =========================
     CREATE EVENT MODAL
========================= --}}
<div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="createEventLabel">
                    Create New Event
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  action="{{ route('programchair.events.store') }}"
                  data-confirm-title="Create Event"
                  data-confirm-message="Create this event with the provided details?">
                @csrf
                <input type="hidden" name="_from" value="create">

                <div class="modal-body">

                    @if ($errors->any() && old('_from') === 'create')
                        <div class="alert alert-danger">
                            <div class="fw-bold mb-1">Please fix the following:</div>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Event Type --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Type <span class="text-danger">*</span></label>
                        <select name="EventType_id"
                                class="form-select @error('EventType_id') is-invalid @enderror"
                                required>
                            <option value="">-- Choose event type --</option>
                            @foreach($eventTypes as $type)
                                <option value="{{ $type->EventType_id }}"
                                    {{ old('EventType_id') == $type->EventType_id ? 'selected' : '' }}>
                                    {{ $type->type_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('EventType_id')
                            @if(old('_from') === 'create')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>

                    {{-- Title --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}"
                               required>
                        @error('title')
                            @if(old('_from') === 'create')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description"
                                  class="form-control @error('description') is-invalid @enderror"
                                  rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            @if(old('_from') === 'create')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Start Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local"
                                   name="start_at"
                                   class="form-control @error('start_at') is-invalid @enderror"
                                   value="{{ old('start_at') }}"
                                   required>
                            @error('start_at')
                                @if(old('_from') === 'create')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @endif
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">End Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local"
                                   name="end_at"
                                   class="form-control @error('end_at') is-invalid @enderror"
                                   value="{{ old('end_at') }}"
                                   required>
                            @error('end_at')
                                @if(old('_from') === 'create')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @endif
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Number of Students (optional)</label>
                        <input type="number"
                               name="number_of_students"
                               class="form-control @error('number_of_students') is-invalid @enderror"
                               value="{{ old('number_of_students', 0) }}"
                               min="0">
                        @error('number_of_students')
                            @if(old('_from') === 'create')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        Save Event
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- =========================
     EDIT EVENT MODAL
========================= --}}
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editEventLabel">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  id="editEventForm"
                  data-confirm-title="Update Event"
                  data-confirm-message="Save changes to this event?">
                @csrf
                @method('PUT')
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Type <span class="text-danger">*</span></label>
                        <select name="EventType_id"
                                id="edit_EventType_id"
                                class="form-select"
                                required>
                            <option value="">-- Choose event type --</option>
                            @foreach($eventTypes as $type)
                                <option value="{{ $type->EventType_id }}">{{ $type->type_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               name="title"
                               id="edit_title"
                               class="form-control"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description"
                                  id="edit_description"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Start Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local"
                                   name="start_at"
                                   id="edit_start_at"
                                   class="form-control"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">End Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local"
                                   name="end_at"
                                   id="edit_end_at"
                                   class="form-control"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Number of Students (optional)</label>
                        <input type="number"
                               name="number_of_students"
                               id="edit_number_of_students"
                               class="form-control"
                               min="0">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- =========================
     EDIT EVENT TYPE MODAL
========================= --}}
<div class="modal fade" id="editEventTypeModal" tabindex="-1" aria-labelledby="editEventTypeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editEventTypeLabel">Edit Event Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  id="editEventTypeForm"
                  data-confirm-title="Update Event Type"
                  data-confirm-message="Save changes to this event type?">
                @csrf
                @method('PUT')

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                        <input type="text"
                               name="type_name"
                               id="edit_event_type_name"
                               class="form-control"
                               required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- =========================
     ASSIGN / INVITE STUDENTS MODAL
========================= --}}
<div class="modal fade" id="assignStudentsModal" tabindex="-1" aria-labelledby="assignStudentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="assignStudentsLabel">
                    Invite Student to Event
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST"
                  action="{{ route('programchair.event-invite') }}"
                  data-confirm-title="Invite Student"
                  data-confirm-message="Send an event invitation to this student?">
                @csrf
                <input type="hidden" name="_from" value="assign">

                <input type="hidden" name="event_id" id="assign_event_id"
                    value="{{ old('_from') === 'assign' ? old('event_id') : '' }}">

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event</label>
                        <input type="text"
                               id="assign_event_title"
                               class="form-control"
                               readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Select Student <span class="text-danger">*</span>
                        </label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Choose a student --</option>
                            @foreach($students as $student)
                                @php
                                    $middle = trim($student->Middle_name ?? '');
                                    $fullName = trim(
                                        ($student->First_name ?? '') . ' ' .
                                        ($middle ? $middle . ' ' : '') .
                                        ($student->Last_name ?? '')
                                    );
                                @endphp
                                <option value="{{ $student->Student_id }}"
                                    {{ old('_from') === 'assign' && old('student_id') == $student->Student_id ? 'selected' : '' }}>
                                    {{ $fullName }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->any() && old('_from') === 'assign')
                            @error('student_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        Invite Student
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- =========================
     VIEW INVITED STUDENTS MODALS (one per event)
========================= --}}
@foreach($events as $event)
<div class="modal fade" id="viewInvitesModal-{{ $event->Event_id }}" tabindex="-1"
     aria-labelledby="viewInvitesLabel-{{ $event->Event_id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="viewInvitesLabel-{{ $event->Event_id }}">
                    Invited Students — {{ $event->title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                {{-- Event Details Section --}}
                <div class="mb-3">
                    <strong>Event Title:</strong> {{ $event->title }}
                </div>
                <div class="mb-3">
                    <strong>Description:</strong> {{ $event->description ?? 'No description available.' }}
                </div>
                <div class="mb-3">
                    <strong>Start Time:</strong> {{ \Carbon\Carbon::parse($event->start_at)->format('M d, Y h:i A') }}
                </div>
                <div class="mb-3">
                    <strong>End Time:</strong> {{ \Carbon\Carbon::parse($event->end_at)->format('M d, Y h:i A') }}
                </div>
                <div class="mb-3">
                    <strong>Slots Available:</strong> {{ $event->number_of_students ?? 'No slots specified.' }}
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> {{ $event->status }}
                </div>

                {{-- Assign Students Button --}}
                <button type="button"
                        class="btn btn-outline-success btn-open-assign"
                        data-bs-toggle="modal"
                        data-bs-target="#assignStudentsModal"
                        data-event-id="{{ $event->Event_id }}"
                        data-event-title="{{ $event->title }}"
                        title="Invite Students"
                        @if($event->assignments->where('status', 'Accepted')->count() >= $event->number_of_students) disabled @endif>
                    <i class="bi bi-person-plus"></i> Invite Students
                </button>

                {{-- Invited Students Table --}}
                @if($event->assignments->isEmpty())
                    <p class="text-muted mb-0">No students invited yet for this event.</p>
                @else
                    <div class="table-responsive mt-3">
                        <table class="invites-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Student Name</th>
                                    <th style="width:140px;">Status</th>
                                    <th style="width:140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($event->assignments as $idx => $assignment)
                                    @php
                                        $s = $assignment->student;
                                        $middle = trim($s->Middle_name ?? '');
                                        $fullName = trim(
                                            ($s->First_name ?? '') . ' ' . 
                                            ($middle ? $middle . ' ' : '') . 
                                            ($s->Last_name ?? '')
                                        );
                                    @endphp

                                    <tr>
                                        <td class="text-center">{{ $idx + 1 }}</td>
                                        <td>{{ $fullName }}</td>
                                        <td class="text-center">{{ $assignment->status }}</td>

                                        <td class="text-center">
                                            @if($assignment->status === 'Pending')
                                                <form method="POST"
                                                    action="{{ route('programchair.event-invite-cancel', [
                                                        'event'  => $event->Event_id,
                                                        'invite' => $assignment->Assignment_id
                                                    ]) }}"
                                                    data-confirm-title="Cancel Invitation"
                                                    data-confirm-message="Cancel this invitation?"
                                                    class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Cancel Invitation">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">No actions available</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endforeach

{{-- =========================
     GENERIC CONFIRMATION MODAL
========================= --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="confirmModalLabel">Please Confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmModalMessage" class="mb-0">Are you sure?</p>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-outline-secondary"
                data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button"
                class="btn btn-primary"
                id="confirmModalYesBtn">
          Yes, Proceed
        </button>
      </div>
    </div>
  </div>
</div>

{{-- =========================
     FEEDBACK MODALS (SUCCESS / ERROR)
========================= --}}
<div class="modal fade" id="feedbackSuccessModal" tabindex="-1" aria-labelledby="feedbackSuccessLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-success">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold" id="feedbackSuccessLabel">Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="feedbackSuccessMessage" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-success"
                data-bs-dismiss="modal">
          OK
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="feedbackErrorModal" tabindex="-1" aria-labelledby="feedbackErrorLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold" id="feedbackErrorLabel">Error</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="feedbackErrorMessage" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-danger"
                data-bs-dismiss="modal">
          OK
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Ensure only one modal is shown at a time
    const modalElements = document.querySelectorAll('.modal');

    // Close all other modals before opening a new one
    modalElements.forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function () {
            modalElements.forEach(function(otherModal) {
                if (otherModal !== modal) {
                    const modalInstance = bootstrap.Modal.getInstance(otherModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            });
        });
    });

    // Re-open modals on validation error (create / assign)
    @if ($errors->any() && old('_from') === 'create')
        const createModalEl = document.getElementById('createEventModal');
        if (createModalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(createModalEl).show();
        }
    @endif

    @if ($errors->any() && old('_from') === 'assign')
        const assignModalEl = document.getElementById('assignStudentsModal');
        if (assignModalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(assignModalEl).show();
        }
    @endif

    const assignEventIdInput    = document.getElementById('assign_event_id');
    const assignEventTitleInput = document.getElementById('assign_event_title');
    const studentSelect         = document.querySelector('#assignStudentsModal select[name="student_id"]');

    const INVITED_BY_EVENT = @json($invitedByEvent);

    // Open assign modal and filter already invited students
    document.querySelectorAll('.btn-open-assign').forEach(function (button) {
        button.addEventListener('click', function () {
            const eventId    = this.getAttribute('data-event-id');
            const eventTitle = this.getAttribute('data-event-title');

            if (assignEventIdInput) {
                assignEventIdInput.value = eventId;
            }
            if (assignEventTitleInput) {
                assignEventTitleInput.value = eventTitle;
            }

            if (studentSelect) {
                studentSelect.value = '';

                const invitedForThisEvent = INVITED_BY_EVENT[eventId] || [];

                studentSelect.querySelectorAll('option').forEach(function (opt) {
                    if (!opt.value) {
                        opt.disabled = false;
                        opt.hidden   = false;
                        return;
                    }

                    const studentId      = Number(opt.value);
                    const alreadyInvited = invitedForThisEvent.includes(studentId);

                    opt.disabled = alreadyInvited;
                    opt.hidden   = alreadyInvited;
                });
            }
        });
    });

    // ============================
    // Edit Event Type - populate
    // ============================
    const editEventTypeForm  = document.getElementById('editEventTypeForm');
    const editEventTypeName  = document.getElementById('edit_event_type_name');
    const eventTypeBaseUrl   = "{{ url('/programchair/event-types') }}";

    document.querySelectorAll('.btn-edit-event-type').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id   = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');

            if (editEventTypeForm) {
                editEventTypeForm.action = eventTypeBaseUrl + '/' + id;
            }
            if (editEventTypeName) {
                editEventTypeName.value = name || '';
            }
        });
    });

    // ============================
    // Edit Event - populate
    // ============================
    const editEventForm          = document.getElementById('editEventForm');
    const eventBaseUrl           = "{{ url('/programchair/events') }}";
    const editEventTypeSelect    = document.getElementById('edit_EventType_id');
    const editTitleInput         = document.getElementById('edit_title');
    const editDescriptionInput   = document.getElementById('edit_description');
    const editStartInput         = document.getElementById('edit_start_at');
    const editEndInput           = document.getElementById('edit_end_at');
    const editSlotsInput         = document.getElementById('edit_number_of_students');

    document.querySelectorAll('.btn-edit-event').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id       = this.getAttribute('data-id');
            const typeId   = this.getAttribute('data-type-id');
            const title    = this.getAttribute('data-title') || '';
            const desc     = this.getAttribute('data-description') || '';
            const start    = this.getAttribute('data-start') || '';
            const end      = this.getAttribute('data-end') || '';
            const slots    = this.getAttribute('data-slots') || 0;

            if (editEventForm) {
                editEventForm.action = eventBaseUrl + '/' + id;
            }
            if (editEventTypeSelect) editEventTypeSelect.value = typeId || '';
            if (editTitleInput)      editTitleInput.value      = title;
            if (editDescriptionInput)editDescriptionInput.value= desc;
            if (editStartInput)      editStartInput.value      = start;
            if (editEndInput)        editEndInput.value        = end;
            if (editSlotsInput)      editSlotsInput.value      = slots;
        });
    });

    // ============================
    // Generic confirmation modal
    // ============================
    const confirmModalEl   = document.getElementById('confirmModal');
    const confirmModal     = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
    const confirmTitleEl   = document.getElementById('confirmModalLabel');
    const confirmMsgEl     = document.getElementById('confirmModalMessage');
    const confirmYesBtn    = document.getElementById('confirmModalYesBtn');
    let confirmTargetForm  = null;

    if (confirmModal && confirmYesBtn) {
        document.querySelectorAll('form[data-confirm-title][data-confirm-message]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === 'true') {
                    return; // already confirmed
                }
                e.preventDefault();

                const title   = form.getAttribute('data-confirm-title') || 'Please Confirm';
                const message = form.getAttribute('data-confirm-message') || 'Are you sure?' ;

                if (confirmTitleEl) confirmTitleEl.textContent = title;
                if (confirmMsgEl)   confirmMsgEl.textContent   = message;

                confirmTargetForm = form;
                confirmModal.show();
            });
        });

        confirmYesBtn.addEventListener('click', function () {
            if (confirmTargetForm) {
                confirmTargetForm.dataset.confirmed = 'true';
                confirmTargetForm.submit();
                confirmTargetForm = null;
            }
        });
    }

    // ============================
    // Feedback modals
    // ============================
    @if(session('success'))
        (function () {
            const el = document.getElementById('feedbackSuccessModal');
            const msgEl = document.getElementById('feedbackSuccessMessage');
            if (el && msgEl && typeof bootstrap !== 'undefined') {
                msgEl.textContent = @json(session('success'));
                new bootstrap.Modal(el).show();
            }
        })();
    @endif

    @if(session('error'))
        (function () {
            const el = document.getElementById('feedbackErrorModal');
            const msgEl = document.getElementById('feedbackErrorMessage');
            if (el && msgEl && typeof bootstrap !== 'undefined') {
                msgEl.textContent = @json(session('error'));
                new bootstrap.Modal(el).show();
            }
        })();
    @endif
});

</script>

@endsection
