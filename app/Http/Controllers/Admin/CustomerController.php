<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->with('user')
            ->withCount(['reservations', 'rentals'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->input('q').'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('national_id', 'like', $like)
                        ->orWhereHas('user', function ($u) use ($like) {
                            $u->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
        ]);
    }

    public function create()
    {
        return view('admin.customers.create', [
            'customer' => null,
            'linkableUsers' => $this->linkableUsers(),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        Customer::create($request->validated());

        return redirect()->route('admin.customers.index')->with('status', 'Customer record created.');
    }

    public function show(Customer $customer)
    {
        $customer->load('user');

        return view('admin.customers.show', [
            'customer' => $customer,
            'reservations' => $customer->reservations()->with('vehicle')->latest()->take(5)->get(),
            'rentals' => $customer->rentals()->with('vehicle')->latest()->take(5)->get(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', [
            'customer' => $customer,
            'linkableUsers' => $this->linkableUsers($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer record updated.');
    }

    public function destroy(Customer $customer)
    {
        $blockers = [];

        if ($customer->reservations()->active()->exists()) {
            $blockers[] = 'a live reservation';
        }

        if ($customer->rentals()->open()->exists()) {
            $blockers[] = 'an open rental';
        }

        $hasPayments = Payment::query()
            ->whereIn('rental_id', $customer->rentals()->select('id'))
            ->exists();
        if ($hasPayments) {
            $blockers[] = 'payment history';
        }

        if ($blockers !== []) {
            return redirect()->back()->with(
                'error',
                'This customer cannot be deleted because they have '.implode(', ', $blockers)
                    .'. Historical records must be preserved.'
            );
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('status', 'Customer record deleted.');
    }

    public function history(Customer $customer)
    {
        $customer->load('user');

        $entries = collect();

        foreach ($customer->reservations()->with('vehicle')->get() as $reservation) {
            $entries->push([
                'date' => $reservation->created_at,
                'kind' => 'reservation',
                'title' => 'Reservation #'.$reservation->id.' — '.($reservation->vehicle->display_name ?? 'Vehicle'),
                'status' => $reservation->status->value,
                'amount' => (float) $reservation->estimated_total,
                'label' => 'Estimated',
            ]);
        }

        foreach ($customer->rentals()->with('vehicle')->get() as $rental) {
            $entries->push([
                'date' => $rental->created_at,
                'kind' => 'rental',
                'title' => 'Rental #'.$rental->id.' — '.($rental->vehicle->display_name ?? 'Vehicle'),
                'status' => $rental->status->value,
                'amount' => (float) $rental->total_amount,
                'label' => 'Total',
                'paid' => $rental->paidAmount(),
                'balance' => $rental->balanceDue(),
            ]);
        }

        $rentalIds = $customer->rentals()->pluck('id');
        foreach (Payment::query()->whereIn('rental_id', $rentalIds)->get() as $payment) {
            $entries->push([
                'date' => $payment->paid_at ?? $payment->created_at,
                'kind' => 'payment',
                'title' => 'Payment '.$payment->receipt_number.' (Rental #'.$payment->rental_id.')',
                'status' => $payment->status->value,
                'amount' => (float) $payment->amount,
                'label' => 'Paid',
            ]);
        }

        $entries = $entries->sortByDesc('date')->values();

        return view('admin.customers.history', [
            'customer' => $customer,
            'entries' => $entries,
        ]);
    }

    private function linkableUsers(?Customer $current = null)
    {
        $linkedIds = Customer::query()->whereNotNull('user_id')->pluck('user_id');

        return User::query()
            ->where('role', UserRole::Customer->value)
            ->when($current, fn ($q) => $q->where(fn ($i) => $i->whereNotIn('id', $linkedIds)->orWhere('id', $current->user_id)))
            ->when(! $current, fn ($q) => $q->whereNotIn('id', $linkedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
