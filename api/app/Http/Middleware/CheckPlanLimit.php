<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit extends Middleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        $allowed = match ($feature) {
            'generate'   => $user?->canGenerate(),
            'mobile'     => $user?->canUseMobile(),
            'custom_ui'  => $user?->canUseCustomUI(),
            'byok'       => $user?->canUseBYOK(),
            default      => true,
        };

        if (!$allowed) {
            return response()->json([
                'message'     => "Your plan doesn't include this feature. Please upgrade.",
                'feature'     => $feature,
                'upgrade_url' => config('app.frontend_url').'/pricing',
            ], 402);
        }

        return $next($request);
    }
}
