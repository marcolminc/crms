@extends('layouts.app')

@section('title', 'Add Vehicle')

@section('content')
    <x-page-header title="Add Vehicle" description="Register a new vehicle in the fleet." />

    <form method="POST" action="{{ route('admin.vehicles.store') }}">
        @csrf
        <x-card>
            @include('admin.vehicles._form')

            <div class="mt-2 flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Save Vehicle
                </button>
                <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection
