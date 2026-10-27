<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many failed attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt($request->authCredentials(), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            // The credentials matched, so the guard has already put the account in
            // this session: throw it away before telling anyone why it is unusable.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact Land Valley\'s Travels support to reactivate it.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Land each actor where its work lives (design doc §2).
        $landing = match (true) {
            $user->isStaff() => route('admin.dashboard'),
            $user->isDriver() => route('notifications.index'),
            default => route('customer.dashboard'),
        };

        $redirect = redirect()->intended($landing);

        if ($user->isDriver()) {
            $redirect = $redirect->with('status', 'Welcome back. The driver trip-log portal (UC24) is under construction; alerts and your profile are available meanwhile.');
        }

        return $redirect;
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been logged out.');
    }
}
