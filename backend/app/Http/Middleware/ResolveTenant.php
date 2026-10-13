<?php
namespace App\\Http\\Middleware;
use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;
class ResolveTenant {
    public function handle(Request $request, Closure $next): Response {
        $user = $request->user();
        if (!$user || !$user->tenant_id) return response()->json(['message'=>'A valid tenant context is required.'], 403);
        // Derive tenant context from authenticated identity, never from client-provided tenant IDs.
        app()->instance('currentTenantId', (int) $user->tenant_id);
        return $next($request);
    }
}
