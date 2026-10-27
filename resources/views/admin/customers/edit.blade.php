@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <x-page-header title="Edit Customer" :description="$customer->display_name">
        <x-slot:actions>
            <a href="{{ route('admin.customers.show', $customer) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to profile</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
        @csrf
        @method('PUT')
        <x-card>
            @include('admin.customers._form')

            <div class="mt-4 flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Update Customer
                </button>
                <a href="{{ route('admin.customers.show', $customer) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection
