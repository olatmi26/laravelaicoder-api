<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    private string $secretKey;
    private string $publicKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->publicKey = config('services.flutterwave.public_key');
    }

    // ── Create a payment link for subscription ──
    public function createPaymentLink(User $user, Plan $plan, string $billingCycle): array
    {
        $amount   = $billingCycle === 'yearly'
            ? ($plan->price_yearly * 12) / 100   // total yearly in dollars
            : $plan->price_monthly / 100;          // monthly in dollars

        $currency = 'USD';
        $txRef    = 'LAC-'.$user->id.'-'.time();

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/payments", [
                'tx_ref'       => $txRef,
                'amount'       => $amount,
                'currency'     => $currency,
                'redirect_url' => config('app.frontend_url').'/billing/callback',
                'customer'     => [
                    'email' => $user->email,
                    'name'  => $user->name,
                ],
                'customizations' => [
                    'title'       => 'LaravelAICoder — '.$plan->name,
                    'description' => ucfirst($billingCycle).' subscription',
                    'logo'        => config('app.frontend_url').'/logo.png',
                ],
                'meta' => [
                    'user_id'       => $user->id,
                    'plan_id'       => $plan->id,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Flutterwave payment link failed', ['response' => $response->json()]);
            throw new \RuntimeException('Payment provider error. Please try again.');
        }

        return $response->json('data');
    }

    // ── Verify a transaction after redirect ──
    public function verifyTransaction(string $transactionId): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

        if (!$response->successful()) {
            throw new \RuntimeException('Could not verify transaction.');
        }

        return $response->json('data');
    }

    // ── Handle webhook event ──
    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? '';

        match ($event) {
            'charge.completed'      => $this->handleChargeCompleted($payload['data']),
            'subscription.cancelled' => $this->handleSubscriptionCancelled($payload['data']),
            default => Log::info('Unhandled Flutterwave event: '.$event),
        };
    }

    // ── Activate subscription after successful payment ──
    public function activateSubscription(User $user, Plan $plan, string $billingCycle, array $flwData): Subscription
    {
        // Cancel any existing active subscription
        $user->subscription()?->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $subscription = Subscription::create([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'flw_subscription_id'  => $flwData['id'] ?? null,
            'flw_plan_id'          => $flwData['plan'] ?? null,
            'status'               => 'active',
            'billing_cycle'        => $billingCycle,
            'amount'               => $flwData['amount'] * 100, // convert to cents
            'currency'             => $flwData['currency'] ?? 'USD',
            'current_period_start' => now(),
            'current_period_end'   => $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
            'flw_data' => $flwData,
        ]);

        // Update user plan
        $user->update(['plan_id' => $plan->id]);

        return $subscription;
    }

    // ── Verify webhook signature ──
    public function verifyWebhookSignature(string $hash): bool
    {
        return $hash === config('services.flutterwave.webhook_hash');
    }

    private function handleChargeCompleted(array $data): void
    {
        $meta    = $data['meta'] ?? [];
        $userId  = $meta['user_id'] ?? null;
        $planId  = $meta['plan_id'] ?? null;
        $cycle   = $meta['billing_cycle'] ?? 'monthly';

        if (!$userId || !$planId) return;

        $user = User::find($userId);
        $plan = Plan::find($planId);
        if (!$user || !$plan) return;

        $this->activateSubscription($user, $plan, $cycle, $data);
        Log::info("Subscription activated: user {$userId} → plan {$plan->slug}");
    }

    private function handleSubscriptionCancelled(array $data): void
    {
        $flwId = $data['id'] ?? null;
        if (!$flwId) return;

        Subscription::where('flw_subscription_id', $flwId)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
