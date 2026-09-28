<?php
namespace Modules\Auth\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Your OTP Code')
            ->html("
                <h2>OTP Verification</h2>
                <p>Your code is:</p>
                <h1>{$this->otp}</h1>
                <p>Expires in 10 minutes</p>
            ");
    }
}