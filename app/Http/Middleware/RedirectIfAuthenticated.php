<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $platform = Auth::user()->platform; 

                if ($platform == 'shopee') {
                    return redirect('/shopee');
                } elseif ($platform == 'lazada') {
                    return redirect('/lazada');
                } elseif ($platform === 'tchub') {
                    return redirect()->route('tchub.index');
                }
            }
        }

        return $next($request);
    }
}
