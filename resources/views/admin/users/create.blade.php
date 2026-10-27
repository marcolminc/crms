@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <x-page-header title="Add User" description="Create a staff or customer account." />

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <x-card>
            @include('admin.users._form')

            <div class="mt-2 flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Create User
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection
