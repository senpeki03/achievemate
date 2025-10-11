<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeansListApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $studentName;
    public ?string $claimUrl;

    public function __construct(string $studentName, ?string $claimUrl = null)
    {
        $this->studentName = $studentName;
        $this->claimUrl    = $claimUrl;
    }

    public function build()
    {
        return $this->subject("Dean’s Lister Approval")
            ->view('emails.deans_list_approved')
            ->with([
                'studentName' => $this->studentName,
                'claimUrl'    => $this->claimUrl,
            ]);
    }
}
