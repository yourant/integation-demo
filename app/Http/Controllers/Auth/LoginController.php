<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\SapConnectionTrait;
use App\Http\Controllers\Controller;
use App\Models\SapUser;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
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

    use AuthenticatesUsers, SapConnectionTrait;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

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
     * Handle an authentication attempt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $credentials = $this->validateLogin($request);

        if ($this->attemptConnection($credentials)) {
            $sapUser = SapUser::getCurrentUser($this->getConnection(), $credentials['user_code']);

            $user = User::updateOrCreate(
                ['user_code' => $credentials['user_code']],
                [
                    'id' => $sapUser['InternalKey'], 
                    'user_name' => $sapUser['UserName'],
                    'email' => $sapUser['eMail'],
                    'mobile_phone_number' => $sapUser['MobilePhoneNumber'],
                    'is_superuser' => User::IS_SUPERUSER[$sapUser['Superuser']],
                ]
            );

            if (Auth::login($user)) {
                $request->session()->regenerate();

                return redirect()->intended('/');
            }
        }

        return back()->withErrors([
            'db' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
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
            'db' => ['required'],
            'user_code' => ['required'],
            'pword' => ['required'],
        ]);
    }
}
