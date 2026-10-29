<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
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

    public function store(LoginRequest $request, AuditLogger $audit)
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many failed attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt($request->authCredentials(), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            $audit->log('auth.login_failed', 'Failed login attempt for '.($request->input('email') ?? 'an unknown account').'.');

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

        $audit->log('auth.login', "Signed in as {$user->email} ({$user->role->label()}).", $user, ['remember' => $request->boolean('remember')]);

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

    public function destroy(Request $request, AuditLogger $audit)
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Capture the account before the session is torn down: after logout()
        // auth()->user() is null, so the actor is handed to the logger explicitly.
        $audit->log('auth.logout', 'Signed out'.($user !== null ? ' '.$user->email : '').'.', $user, [], $user);

        return redirect()->route('home')->with('status', 'You have been logged out.');
    }
}
