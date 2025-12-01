@component('mail::message')
# Hello {{ $student->First_name }},

You have been invited to take part in the following event:

---

### **{{ $event->title }}**

@if($event->eventType)
**📌 Type:** {{ $event->eventType->type_name }}
@endif

**📅 Date:** {{ \Carbon\Carbon::parse($event->start_at)->format('F d, Y') }}  
**⏰ Time:** 
{{ \Carbon\Carbon::parse($event->start_at)->format('h:i A') }} –
{{ \Carbon\Carbon::parse($event->end_at)->format('h:i A') }}

@if($event->venue)
**📍 Venue:** {{ $event->venue }}
@endif

@if($event->description)
**📝 Description:**  
{{ $event->description }}
@endif

**👤 Invited by:** {{ $inviterName }}

---

@component('mail::button', ['url' => url('/student/event-invites')])
View Invitation
@endcomponent

Sincerely,  
**AchieveMate Administrator**
@endcomponent
