<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IsAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('authenticated'))
        {
            if ($request->is('login')) { return $next($request); }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired'], 401);
            }
            return redirect('/login');
        }
        else
        {
            if ($request->is('login')) { return redirect('/admin'); }
        }
        return $next($request);
    }
}
