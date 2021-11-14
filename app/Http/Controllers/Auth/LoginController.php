<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @var void
     */
    protected function loggedOut(Request $request) {
        return redirect('/login');
    }

    /**
     * Where to redirect users after login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @var void
     */
    public function authenticated(Request $request, $user) {
        if ($user->platform == 'shopee') {
            return redirect()->route('shopee.dashboard');
        } elseif ($user->platform == 'lazada') {
            return redirect()->route('lazada.dashboard');
        } elseif ($user->platform === 'tchub') {
            if ($user->tokens->isEmpty())
            {
                $user->createToken('tchubToken');
            }
            return redirect()->route('tchub.index');
        } else {
            abort(403);
        }
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateLogin(Request $request)
    {
        return $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);
    }
}
