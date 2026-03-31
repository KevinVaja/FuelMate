<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAgentApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAgent()) {
            return $next($request);
        }

        $agent = $user->agent;

        if (! $agent || ! $agent->isApprovedForOperations()) {
            if ($agent && $agent->is_available) {
                $agent->update(['is_available' => false]);
            }

            return redirect()
                ->route('agent.dashboard')
                ->with('error', 'Your petrol pump account is under verification');
        }

        return $next($request);
    }
}
