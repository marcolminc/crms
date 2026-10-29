@extends('layouts.app')

@section('title', 'Edit Vehicle')

@section('content')
    <x-page-header title="Edit Vehicle" description="Update the details for {{ $vehicle->plate_number }}." />

    <form method="POST" action="{{ route('admin.vehicles.update', $vehicle) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-card>
            @include('admin.vehicles._form')

            <div class="mt-2 flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Update Vehicle
                </button>
                <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection
