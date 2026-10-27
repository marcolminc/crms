<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
        ]);
    }

    public function create()
    {
        return view('admin.users.create', [
            'user' => null,
            'roles' => UserRole::options(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        User::create($request->validated());

        return redirect()->route('admin.users.index')->with('status', 'User account created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::options(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return redirect()->back()->with('error', 'This is the last active administrator; the account cannot be deleted.');
        }

        $customer = $user->customer;

        if ($customer !== null && ($customer->reservations()->exists() || $customer->rentals()->exists())) {
            return redirect()->back()->with('error', 'This account belongs to a customer with booking history. Deactivate it instead of deleting, so the records stay available for reporting.');
        }

        $customer?->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    public function activate(User $user)
    {
        $user->update(['is_active' => true]);

        return redirect()->back()->with('status', "Account for {$user->name} activated.");
    }

    public function deactivate(User $user)
    {
        if ($user->is(auth()->user())) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return redirect()->back()->with('error', 'This is the last active administrator; the account cannot be deactivated.');
        }

        $user->update(['is_active' => false]);

        return redirect()->back()->with('status', "Account for {$user->name} deactivated.");
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if (! $user->is_active || ! $user->hasRole(UserRole::Admin)) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
