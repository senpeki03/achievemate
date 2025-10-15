<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeansListApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $studentName;
    public string $studentId;
    public string $program;
    public string $yearLevel;
    public string $college;
    public string $gwa;
    public string $rankOrDistinction;
    public string $term;
    public string $ay;
    public string $downloadLink;
    public string $universityName;
    public string $deanName;
    public string $deanTitle;
    public string $systemName;
    public string $supportEmail;

    public function __construct(array $data)
    {
        $this->studentName = $data['studentName'];
        $this->studentId = $data['studentId'];
        $this->program = $data['program'];
        $this->yearLevel = $data['yearLevel'];
        $this->college = $data['college'];
        $this->gwa = $data['gwa'];
        $this->rankOrDistinction = $data['rankOrDistinction'];
        $this->term = $data['term'];
        $this->ay = $data['ay'];
        $this->downloadLink = $data['downloadLink'];
        $this->universityName = $data['universityName'];
        $this->deanName = $data['deanName'];
        $this->deanTitle = $data['deanTitle'];
        $this->systemName = $data['systemName'];
        $this->supportEmail = $data['supportEmail'];
    }

    public function build()
    {
        return $this->subject("Official Confirmation – Dean’s Honor List {$this->term}, AY {$this->ay}")
            ->view('emails.deans_list_approved')
            ->with([
                'studentName' => $this->studentName,
                'studentId' => $this->studentId,
                'program' => $this->program,
                'yearLevel' => $this->yearLevel,
                'college' => $this->college,
                'gwa' => $this->gwa,
                'rankOrDistinction' => $this->rankOrDistinction,
                'term' => $this->term,
                'ay' => $this->ay,
                'downloadLink' => $this->downloadLink,
                'universityName' => $this->universityName,
                'deanName' => $this->deanName,
                'deanTitle' => $this->deanTitle,
                'systemName' => $this->systemName,
                'supportEmail' => $this->supportEmail,
            ]);
    }
}
