<?php

namespace TCG\Voyager\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;

class VoyagerLaporanMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        
        $route_name = Route::currentRouteName();
        
        if (!Auth::guest()) {
            $user = auth()->user();
            if (isset($user->locale)) {
                app()->setLocale($user->locale);
            }

            if($user->hasPermission('browse_laporan') && $user->hasPermission($route_name))
            {
                return $next($request);
            }else{
                return abort(403);
            }
        }

        abort(403);
    }
}
