<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

class VerifyApiEmail extends VerifyEmailBase
{
    protected $hasOldEmail;

    /**
     * VerifyEmail constructor.
     *
     * @param bool $hasOldEmail
     */
    public function __construct(bool $hasOldEmail)
    {
        $this->hasOldEmail = $hasOldEmail;
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }
        $data = [
            'email' => $notifiable->email,
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
        ];
        DB::table('verification_email')->insert($data);

        return (new MailMessage)
            ->greeting(Lang::get('mails.greeting', ['name' => $notifiable->firstname]))
            ->subject(Lang::get('mails.subject'))
            ->line(Lang::get('mails.line1', ['email' => $notifiable->email]))
            ->action(Lang::get('mails.button_text'), $verificationUrl)
            ->line(Lang::get('mails.line2'))
            ->line(new HtmlString('After you Login and Verify your account, you must <strong>Complete Your Registration</strong> in order to continue Using Our Apps.'));
    }

    protected function verificationUrl($notifiable)
    {
        $expires = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60));
        $hash = sha1($notifiable->getEmailForVerification());
        $email = $notifiable->email;

        return env('FRONT_URL') . 'email/verify/' . $email . '/' . $hash . '/' . strtotime($expires);
    }
}
