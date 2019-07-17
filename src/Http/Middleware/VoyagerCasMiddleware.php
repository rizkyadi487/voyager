<?php

namespace TCG\Voyager\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Subfission\Cas\Facades\Cas;

class VoyagerCasMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if( !cas()->checkAuthentication() )
        {
            if ($request->ajax()) {
                return response('Unauthorized.', 401);
            }
            cas()->authenticate();

            if (!cas()->checkAuthentication()) {
                return response('Unauthorized.', 401);
            }
        }
        session()->put('cas_user', cas()->user() );

        if (Auth::guard($guard)->check()) {
            $user = Auth::user();
        }else{
            $id_paspor = Cas::getCurrentUser();
            $user = User::where('username', $id_paspor)->firstOrFail();
            if(!$user){
                abort(403);
            }
            Auth::login($user);
        }

        return $next($request);
    }
}
