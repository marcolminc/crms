<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;

final class PasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request)
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()->route('profile.edit')->with('status', 'Your password has been updated.');
    }
}
