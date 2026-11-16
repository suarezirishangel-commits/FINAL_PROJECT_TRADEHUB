<?php

namespace App\Support;

use App\Mail\OrderConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderConfirmationSender
{
    public function __construct(private TradeHubContent $content) {}

    public function send(User $user, string $reference, array $items, float $total, string $paymentMethod): bool
    {
        $mailer = config('mail.default');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');

        if ($mailer !== 'smtp' || ! $username || ! $password) {
            Log::warning('TradeHub order email was not sent because SMTP is not configured.', [
                'reference' => $reference,
                'recipient' => $user->email,
            ]);

            return false;
        }

        try {
            Mail::to($user->email)->send(new OrderConfirmation(
                $user,
                $reference,
                $items,
                $total,
                $paymentMethod,
                $this->content->setting('site_name', 'TradeHub'),
            ));

            return true;
        } catch (Throwable $error) {
            Log::error('TradeHub order email could not be sent.', [
                'reference' => $reference,
                'recipient' => $user->email,
                'message' => $error->getMessage(),
            ]);

            return false;
        }
    }
}
