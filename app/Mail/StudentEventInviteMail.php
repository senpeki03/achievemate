<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\StudentManage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentEventInviteMail extends Mailable
{
   use Queueable, SerializesModels;

    public $event;
    public $student;
    public $inviterName;

    public function __construct(Event $event, StudentManage $student, $inviterName)
    {
        $this->event       = $event;
        $this->student     = $student;
        $this->inviterName = $inviterName;
    }

    public function build()
    {
        return $this->subject('You Have Been Invited to an Event')
                    ->markdown('emails.student_event_invite');
    }
  }
