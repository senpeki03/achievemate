<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;

    /**
     * Create a new message instance.
     *
     * @param array $details
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your Registration on AchieveMate Online Services')
                    ->view('emails.student_registration')
                    ->with([
                        'logo' => $this->details['logo'],
                        'fullname' => $this->details['fullname'],
                        'title' => $this->details['title'],
                        'loginUrl' => $this->details['loginUrl'],
                        'username' => $this->details['username'],
                        'password' => $this->details['password'],
                        'usertype' => $this->details['usertype'],
                    ]);
    }
}
