<?php

namespace TCG\Voyager\Http\Middleware;

use App\Models\UserRole;
use App\Models\UserScope;
use App\User;
use Carbon\Carbon;
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
        if (!cas()->checkAuthentication()) {
            if ($request->ajax()) {
                return response('Unauthorized.', 401);
            }
            cas()->authenticate();
        }
        session()->put(cas()->user(), cas()->user());

        if (Auth::guard($guard)->check()) {
            $user = Auth::user();
        } else {
            $id_paspor = Cas::getCurrentUser();

            $userpaspor = \DB::connection('ssodb-simpkb')->table('users')
                ->where('userid', '=', $id_paspor)->first();

            $user = User::where('email', $userpaspor->email)->first();
            if (!$user) {
                $cas_user = User::updateOrCreate([
                    'email' => $userpaspor->email,
                ], [
                    'role_id'   => 0,
                    'name'      => $userpaspor->nama,
                    'email'     => $userpaspor->email,
                    'avatar'    => 'users/default.png',
                    'password'  => bcrypt('password'),
                    'paspor_id' => $userpaspor->userid,
                ]);

                $id_user = $cas_user->getKey();

                UserRole::where('user_id', '=', (int)$id_user)
                    ->whereNull('is_kastem')
                    ->delete();

                \Artisan::call('map:user_scopes', ['--email' => $userpaspor->email, '--connection' => 'gpodb']);
                \Artisan::call('map:user_scopes', ['--email' => $userpaspor->email, '--connection' => 'guruberbagidb']);

                $user_scopes = UserScope::where('email', '=', $userpaspor->email)
                    ->select('k_group')
                    ->get();

                if (count($user_scopes) == 0) {
                    abort(401, 'Mohon maaf anda tidak memiliki hak akses');
                }

                $is_pusat = false;

                foreach ($user_scopes as $user_scope) {
                    UserRole::updateOrCreate([
                        'user_id' => $id_user,
                        'role_id' => $user_scope->k_group,
                    ], [
                        'user_id' => $id_user,
                        'role_id' => $user_scope->k_group,
                    ]);

                    if (in_array((int)$user_scope->k_group, [5, 22, 27, 28, 37, 42])) {
                        $is_pusat = true;
                    }
                }

                if ($is_pusat == true) {
                    UserRole::updateOrCreate([
                        'user_id' => $id_user,
                        'role_id' => 99,
                    ], [
                        'user_id' => $id_user,
                        'role_id' => 99,
                    ]);
                }

                \Auth::login($cas_user);
            } else {
                User::where('email', $userpaspor->email)
                    ->update([
                        'paspor_id' => $userpaspor->userid,
                        'last_login' => Carbon::now(),
                    ]);
                Auth::login($user, false);
            }
        }
        if ($user->role->is_blokir) {
            Auth::logout();
            abort(403);
        }
        return $next($request);
    }
}
