<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\FlutterwaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ─────────────────────────────────────────────
// WorkspaceController
// ─────────────────────────────────────────────
class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withCount('projects')
            ->latest()
            ->get();

        return response()->json(['workspaces' => $workspaces]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace = $request->user()->workspaces()->create($data);

        return response()->json(['workspace' => $workspace], 201);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);
        return response()->json(['workspace' => $workspace->load('projects')]);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $workspace->update($data);
        return response()->json(['workspace' => $workspace]);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);
        $workspace->delete();
        return response()->json(['message' => 'Workspace deleted.']);
    }
}

// ─────────────────────────────────────────────
// BillingController
// ─────────────────────────────────────────────
class BillingController extends Controller
{
    public function __construct(private FlutterwaveService $flw) {}

    // GET /api/v1/billing/plans
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json(['plans' => $plans]);
    }

    // POST /api/v1/billing/subscribe
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_slug'     => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('slug', $data['plan_slug'])->firstOrFail();

        if ($plan->price_monthly === 0) {
            return response()->json(['message' => 'Starter plan is free — no payment needed.'], 400);
        }

        $paymentData = $this->flw->createPaymentLink(
            $request->user(),
            $plan,
            $data['billing_cycle']
        );

        return response()->json([
            'payment_link' => $paymentData['link'],
            'tx_ref'       => $paymentData['tx_ref'] ?? null,
        ]);
    }

    // POST /api/v1/billing/verify  — called after Flutterwave redirect
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'string'],
            'plan_slug'      => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle'  => ['required', 'in:monthly,yearly'],
        ]);

        $txData = $this->flw->verifyTransaction($data['transaction_id']);

        if (($txData['status'] ?? '') !== 'successful') {
            return response()->json(['message' => 'Payment was not successful.'], 400);
        }

        $plan = Plan::where('slug', $data['plan_slug'])->firstOrFail();
        $sub  = $this->flw->activateSubscription(
            $request->user(),
            $plan,
            $data['billing_cycle'],
            $txData
        );

        return response()->json([
            'message'      => 'Subscription activated!',
            'subscription' => $sub,
            'plan'         => $plan,
        ]);
    }

    // POST /api/v1/billing/webhook  — Flutterwave calls this
    public function webhook(Request $request): JsonResponse
    {
        $hash = $request->header('verif-hash');

        if (!$this->flw->verifyWebhookSignature($hash)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $this->flw->handleWebhook($request->all());

        return response()->json(['status' => 'ok']);
    }

    // GET /api/v1/billing/portal
    public function portal(Request $request): JsonResponse
    {
        $sub = $request->user()->activeSubscription;

        return response()->json([
            'subscription' => $sub,
            'plan'         => $request->user()->plan,
            'manage_url'   => 'https://app.flutterwave.com', // FLW doesn't have a portal URL; link to FLW dashboard
        ]);
    }
}
