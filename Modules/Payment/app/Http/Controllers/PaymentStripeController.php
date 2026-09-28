<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Appointments\Models\Appointment;
use Modules\Core\Http\Controllers\Controller;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class PaymentStripeController extends Controller
{
    /**
     * Stripe Checkout return URL for appointment (and other) card payments.
     * GET /api/payment/stripe/success?session_id=...
     */
    public function success(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $confirmed = false;

        try {
            Stripe::setApiKey((string) config('services.stripe.secret'));
            $session = Session::retrieve($request->session_id);

            if (($session->payment_status ?? null) === 'paid') {
                $referenceType = (string) ($session->metadata->reference_type ?? '');
                $referenceId = (int) ($session->metadata->reference_id ?? 0);

                if ($referenceType === 'appointment' && $referenceId > 0) {
                    $appointment = Appointment::query()
                        ->where('id', $referenceId)
                        ->where('status', 'awaiting_payment')
                        ->first();

                    if ($appointment) {
                        $appointment->update([
                            'status' => 'pending',
                            'payment_method' => 'stripe',
                        ]);
                        $confirmed = true;
                    }
                } else {
                    $confirmed = true;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $title = $confirmed ? 'Payment successful' : 'Payment received';
        $body = $confirmed
            ? 'Your appointment payment was confirmed. You can close this page and return to the app.'
            : 'You can close this page and return to the app. If the appointment is still unpaid, refresh your bookings.';

        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.$title.'</title></head><body style="font-family:sans-serif;text-align:center;padding:48px;background:#f8fafc;color:#0f172a">'
            .'<h1 style="margin-bottom:12px">'.$title.'</h1>'
            .'<p style="color:#475569;line-height:1.5">'.$body.'</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Stripe Checkout cancel URL.
     * GET /api/payment/stripe/cancel
     */
    public function cancel()
    {
        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Payment cancelled</title></head><body style="font-family:sans-serif;text-align:center;padding:48px;background:#f8fafc;color:#0f172a">'
            .'<h1 style="margin-bottom:12px">Payment cancelled</h1>'
            .'<p style="color:#475569;line-height:1.5">No charge was made. You can close this page and try again from the app.</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
