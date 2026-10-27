@extends('layouts.app')

@section('title', 'Edit Maintenance Record')

@section('content')
    <x-page-header title="Edit Maintenance Record" description="{{ $maintenance->title }}" />

    <form method="POST" action="{{ route('admin.maintenances.update', $maintenance) }}">
        @csrf
        @method('PUT')
        <x-card>
            @include('admin.maintenances._form')

            <div class="mt-2 flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Update Record
                </button>
                <a href="{{ route('admin.maintenances.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection
