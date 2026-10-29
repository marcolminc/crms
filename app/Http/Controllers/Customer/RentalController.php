<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ExtendRentalRequest;
use App\Http\Requests\Customer\RateRentalRequest;
use App\Models\Rental;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\OnlinePaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class RentalController extends Controller
{
    public function show(Request $request, Rental $rental, OnlinePaymentService $checkout)
    {
        $this->ensureOwnership($request, $rental);

        $rental->load([
            'vehicle',
            'reservation',
            'payments',
            'driver',
            'vehicleReturn.vehicle',
            'vehicleReturn.processedBy',
            'fines.vehicle',
            'fines.recorder',
        ]);

        return view('customer.rentals.show', [
            'rental' => $rental,
            'extendable' => $this->isExtendable($rental),
            'onlinePayments' => $checkout->enabled(),
            'onlineMethods' => array_intersect_key(PaymentMethod::options(), array_flip(
                array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::onlineCapable())
            )),
        ]);
    }

    /**
     * FR9: the customer's copy of the settlement invoice for their own hire.
     */
    public function invoice(Request $request, Rental $rental)
    {
        $this->ensureOwnership($request, $rental);

        $rental->load([
            'vehicle',
            'customer.user',
            'payments',
            'fines',
            'vehicleReturn',
        ]);

        return view('customer.rentals.invoice', [
            'rental' => $rental,
            'paid' => $rental->paidAmount(),
            'balance' => $rental->balanceDue(),
            'surplus' => max(0, $rental->paidAmount() - (float) $rental->total_amount),
        ]);
    }

    /**
     * UC10: keep the same vehicle for extra days. The new balance is picked up
     * by the existing online-payment flow on this page.
     */
    public function extend(ExtendRentalRequest $request, Rental $rental, NotificationService $notifications, AuditLogger $audit)
    {
        $this->ensureOwnership($request, $rental);

        if (! $this->isExtendable($rental)) {
            return back()->with('error', 'This rental can no longer be extended.');
        }

        $previousEnd = $rental->end_date->copy();
        $newEnd = Carbon::createFromFormat('Y-m-d', (string) $request->validated('end_date'))->startOfDay();

        $rental->end_date = $newEnd->toDateString();
        $rental->days = (int) $rental->start_date->diffInDays($newEnd) + 1;
        $rental->recalculate();
        $rental->save();

        $extraDays = (int) $previousEnd->diffInDays($newEnd);

        $notifications->forUsers(
            $notifications->bookingDesk(),
            'Rental extended by customer',
            ($rental->customer?->display_name ?? 'A customer')
                .' extended rental #'.$rental->id
                .' for '.($rental->vehicle?->display_name ?? 'a vehicle')
                .' by '.$extraDays.' day(s), now due '.$newEnd->format('d M Y')
                .'. New balance '.number_format($rental->balanceDue(), 0).' TZS.',
            route('admin.rentals.show', $rental),
            'rental_extended'
        );

        $audit->log(
            'rental.customer_extended',
            'Customer extended rental #'.$rental->id.' to '.$newEnd->format('Y-m-d').' ('.$extraDays.' extra day(s)).',
            $rental
        );

        return back()->with('status', 'Your rental has been extended to '.$newEnd->format('d M Y').'. Pay the new balance to confirm the extra days.');
    }

    /**
     * UC21: rate a returned hire once.
     */
    public function rate(RateRentalRequest $request, Rental $rental, AuditLogger $audit)
    {
        $this->ensureOwnership($request, $rental);

        if (! $rental->isRateable()) {
            return back()->with('error', 'This rental cannot be rated.');
        }

        $rental->forceFill([
            'rating' => (int) $request->validated('rating'),
            'rating_comment' => $request->validated('rating_comment'),
            'rated_at' => now(),
        ])->save();

        $audit->log('rental.rated', 'Rental #'.$rental->id.' rated '.$rental->rating.'/5 by the customer.', $rental);

        return back()->with('status', 'Thank you for rating your rental.');
    }

    /**
     * UC09 (customer side): the driver tells us the car has been dropped off.
     * This is only a signal - the desk still performs the inspection and the
     * formal return. We record the request and nudge the hire desk to check
     * the vehicle in.
     */
    public function requestReturn(Request $request, Rental $rental, NotificationService $notifications, AuditLogger $audit)
    {
        $this->ensureOwnership($request, $rental);

        if (! $rental->canRequestReturn()) {
            return back()->with('error', 'This rental cannot be marked as returned right now.');
        }

        $rental->update(['return_requested_at' => now()]);

        $vehicleName = $rental->vehicle?->display_name ?? 'the vehicle';

        $notifications->forUsers(
            $notifications->bookingDesk(),
            'Customer reports vehicle returned',
            ($rental->customer?->display_name ?? 'A customer')
                .' says they have dropped off '.$vehicleName
                .' (rental #'.$rental->id.'. Please inspect and complete the return.',
            route('admin.returns.create', $rental),
            'return_requested'
        );

        $audit->log(
            'rental.return_requested',
            'Customer reported rental #'.$rental->id.' as returned, pending desk inspection.',
            $rental
        );

        return back()->with('status', 'Thanks for letting us know. Our team will inspect the vehicle and confirm your return shortly.');
    }

    private function isExtendable(Rental $rental): bool
    {
        return in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true);
    }

    private function ensureOwnership(Request $request, Rental $rental): void
    {
        $customer = $request->user()->customer;

        abort_unless($customer && $rental->customer_id === $customer->id, 404);
    }
}
