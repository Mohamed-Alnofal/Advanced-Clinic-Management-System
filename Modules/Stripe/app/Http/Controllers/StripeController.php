<?php

namespace Modules\Stripe\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\WalletTransaction\Models\WalletTransaction;

class StripeController extends Controller
{
   public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        $event = Webhook::constructEvent(
            $payload,
            $sig,
            config('services.stripe.webhook')
        );

        if ($event->type === 'checkout.session.completed') {

            $session = $event->data->object;

            $transaction = WalletTransaction::find(
                $session->metadata->transaction_id
            );

            if ($transaction && $transaction->status !== 'success') {

                $transaction->update(['status' => 'success']);

                $wallet = $transaction->wallet;

                $wallet->increment('balance', $transaction->amount);
            }
        }

        return response()->json(['ok']);
    }
    public function index()
    {
        return view('stripe::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stripe::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('stripe::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('stripe::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
