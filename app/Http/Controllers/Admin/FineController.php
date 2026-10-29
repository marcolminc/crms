<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FineLiability;
use App\Enums\FineStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fine\StoreFineRequest;
use App\Models\Rental;
use App\Models\TrafficFine;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * UC25: traffic fines raised at return inspection. A fine is logged first, then
 * liability is decided - billed to the customer (added to the rental) or
 * absorbed by the company. Agents, managers and finance run this queue; the
 * decision is written to the FR14 audit trail and the customer is told.
 */
final class FineController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize($request);

        $fines = TrafficFine::query()
            ->with(['rental.customer.user', 'vehicle', 'recorder', 'decider'])
            ->when($request->filled('status'), function ($q) use ($request) {
                if (array_key_exists($request->input('status'), FineStatus::options())) {
                    $q->where('status', $request->input('status'));
                }
            })
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', FineStatus::Pending->value))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.fines.index', [
            'fines' => $fines,
            'statuses' => FineStatus::options(),
            'pendingCount' => TrafficFine::query()->where('status', FineStatus::Pending->value)->count(),
            'outstandingBilled' => (float) TrafficFine::query()->where('status', FineStatus::Billed->value)->sum('amount'),
            'absorbedTotal' => (float) TrafficFine::query()->where('status', FineStatus::Absorbed->value)->sum('amount'),
        ]);
    }

    public function create(Request $request, Rental $rental)
    {
        $this->authorize($request);

        $rental->load(['vehicle', 'customer.user']);

        return view('admin.fines.create', [
            'rental' => $rental,
            'existingFines' => $rental->fines()->with('recorder')->latest('id')->get(),
        ]);
    }

    public function store(StoreFineRequest $request, Rental $rental)
    {
        $this->authorize($request);

        $fine = new TrafficFine($request->validated());
        $fine->rental_id = $rental->id;
        $fine->vehicle_id = $rental->vehicle_id;
        $fine->status = FineStatus::Pending;
        $fine->recorded_by = $request->user()?->id;
        $fine->save();

        $this->audit->log(
            'fine.recorded',
            'Recorded a '.number_format((float) $fine->amount, 0).' TZS traffic fine on rental #'.$rental->id.'.',
            $fine,
            ['amount' => (float) $fine->amount, 'reference' => $fine->reference],
        );

        return redirect()->route('admin.fines.create', $rental)
            ->with('status', 'Traffic fine recorded. Assign liability when ready.');
    }

    public function bill(Request $request, TrafficFine $fine)
    {
        $this->authorize($request);

        if (! $fine->isPending()) {
            return redirect()->back()->with('error', 'This fine has already been decided.');
        }

        $rental = $fine->rental;

        $fine->update([
            'liability' => FineLiability::Customer,
            'status' => FineStatus::Billed,
            'decided_by' => $request->user()?->id,
            'decided_at' => now(),
        ]);

        // Bill the customer: fold the fine into the rental's additional charges.
        if ($rental !== null) {
            $rental->additional_charges = (float) $rental->additional_charges + (float) $fine->amount;
            $rental->recalculate();
            $rental->save();

            $this->notifications->forCustomer(
                $rental->customer,
                'Traffic fine billed',
                'A '.number_format((float) $fine->amount, 0).' TZS traffic fine has been added to your rental. '
                    .'Revised total: '.number_format((float) $rental->total_amount, 0).' TZS.',
                route('customer.rentals.index'),
                'fine_billed',
            );
        }

        $this->audit->log(
            'fine.billed',
            'Billed a '.number_format((float) $fine->amount, 0).' TZS traffic fine to the customer on rental #'.$fine->rental_id.'.',
            $fine,
            ['amount' => (float) $fine->amount, 'liability' => FineLiability::Customer->value],
        );

        return redirect()->back()->with('status', 'The fine has been billed to the customer.');
    }

    public function absorb(Request $request, TrafficFine $fine)
    {
        $this->authorize($request);

        if (! $fine->isPending()) {
            return redirect()->back()->with('error', 'This fine has already been decided.');
        }

        $fine->update([
            'liability' => FineLiability::Company,
            'status' => FineStatus::Absorbed,
            'decided_by' => $request->user()?->id,
            'decided_at' => now(),
        ]);

        $this->audit->log(
            'fine.absorbed',
            'The company absorbed a '.number_format((float) $fine->amount, 0).' TZS traffic fine on rental #'.$fine->rental_id.'.',
            $fine,
            ['amount' => (float) $fine->amount, 'liability' => FineLiability::Company->value],
        );

        return redirect()->back()->with('status', 'The fine has been absorbed by the company.');
    }

    private function authorize(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::CounterAgent, UserRole::BranchManager, UserRole::Finance, UserRole::Admin) ?? false,
            403,
            'Only agents, managers and finance can handle traffic fines.'
        );
    }
}
