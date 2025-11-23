<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserManagement\Provider;
use Symfony\Component\HttpFoundation\Response;

class IsProvider
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user() instanceof Provider) {
            return $next($request);
        }
        return response()->json(['message' => 'غير مصرح'], 403);
    }
}
