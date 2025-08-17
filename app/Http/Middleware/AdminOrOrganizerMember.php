<?php

namespace App\Http\Middleware;

use App\Enums\RoleNameEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrOrganizerMember
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401);
        }
        
        // Allow platform admins
        if ($user->hasRole(RoleNameEnum::ADMIN)) {
            return $next($request);
        }
        
        // Allow organizer members
        if ($user->hasOrganizerMembership()) {
            return $next($request);
        }
        
        abort(403);
    }
}