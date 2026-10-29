<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * FR1 "authenticate": self-service password recovery via signed email links.
 * Tokens live in the password_reset_tokens table (already shipped with the
 * users migration) and are brokered by Laravel's Password facade.
 */
final class PasswordResetController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, NotificationService $notifications, AuditLogger $audit)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ]);

        // The broker mails an Illuminate\Auth\Notifications\ResetPassword
        // link; with MAIL_MAILER=log in .env it lands in storage/logs/laravel.log.
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => 'We could not send the reset link. Please try again shortly.']);
        }

        // Email delivery may be unavailable (sandbox / no SMTP), so an
        // administrator is always alerted that someone asked to recover their
        // account. This is administration work (managing user accounts), and the
        // admin can set a new password from the account edit page to demonstrate
        // the change-of-password process end to end.
        $account = User::query()->where('email', $request->input('email'))->first();

        if ($account !== null) {
            $notifications->forUsers(
                $notifications->administrators(),
                'Password reset requested',
                $account->name.' ('.$account->email.') asked to reset their password. Open the account to set a new password so they can sign in.',
                route('admin.users.edit', $account),
                'password_reset_requested',
            );

            $audit->log('auth.password_reset_requested', "Password reset requested for {$account->email}.", $account, [], null);
        }

        return back()->with('status', 'A password reset link has been sent to '.$request->input('email').'. Check your inbox (and spam folder).');
    }

    public function createReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['password' => 'new password']);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Rotating the remember token drops any "remember me"
                    // cookies the old password was keeping alive.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordResetEvent($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can log in with your new password.');
    }
}
