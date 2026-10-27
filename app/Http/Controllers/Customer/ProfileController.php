<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('customer.profile', [
            'user' => $request->user(),
            'customer' => $request->user()->customer,
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $user->update(Arr::only($data, ['name', 'email', 'phone']));

        if (! $user->isStaff()) {
            $customer = $user->customer ?? $user->customer()->create([]);
            $customer->update(Arr::except($data, ['name', 'email', 'phone']));
        }

        return redirect()->route('profile.edit')->with('status', 'Your profile has been updated.');
    }
}
