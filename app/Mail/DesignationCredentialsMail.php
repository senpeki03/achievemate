<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DesignationCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title, $fullname, $username, $password, $loginUrl, $usertype, $logo;

    public function __construct($title, $fullname, $username, $password, $loginUrl, $usertype)
    {
        $this->title = $title;
        $this->fullname = $fullname;
        $this->username = $username;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
        $this->usertype = $usertype;
    }

    public function build()
    {
        $logoUrl = 'https://res.cloudinary.com/dtjjgiitl/image/upload/q_auto:good,f_auto,fl_progressive/v1752635667/e4a8qcta38gq8npdcige.jpg'; // ✅ Direct image link

        return $this->view('emails.designation_credentials')
            ->subject('Your Registration on AchieveMate Online Services')
            ->with([
                'title' => $this->title,
                'fullname' => $this->fullname,
                'username' => $this->username,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl,
                'usertype' => $this->usertype,
                'logo' => $logoUrl,
            ]);
    }


}


