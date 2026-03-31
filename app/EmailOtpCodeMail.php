<?php

namespace App;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailOtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $purposeLabel,
        public readonly int $ttlMinutes,
        public readonly ?string $recipientName = null,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Your FuelMate ' . $this->purposeLabel . ' code')
            ->view('email-otp-code');
    }
}
