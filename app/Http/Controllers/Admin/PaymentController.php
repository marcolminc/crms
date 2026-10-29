<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\AuditLogger;
use App\Services\PaymentDraft;
use App\Services\PaymentRecordingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    private const COUNTED_STATUSES = [
        PaymentStatus::Pending->value,
        PaymentStatus::Partial->value,
        PaymentStatus::Paid->value,
    ];

    public function __construct(private readonly PaymentRecordingService $payments) {}

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        $payments = (clone $query)
            ->with(['rental.customer.user', 'rental.vehicle', 'recordedBy'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $collected = (float) (clone $query)->whereIn('status', self::COUNTED_STATUSES)->sum('amount');

        $openIds = Rental::query()->open()->pluck('id');
        $openTotal = (float) Rental::query()->whereIn('id', $openIds)->sum('total_amount');
        $openPaid = (float) Payment::query()
            ->whereIn('rental_id', $openIds)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->sum('amount');

        return view('admin.payments.index', [
            'payments' => $payments,
            'collected' => $collected,
            'outstanding' => max(0, $openTotal - $openPaid),
            'openRentals' => $openIds->count(),
            'statuses' => PaymentStatus::options(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function create(Request $request, Rental $rental)
    {
        $rental->load(['customer.user', 'vehicle']);

        if ($rental->status === RentalStatus::Cancelled) {
            return redirect()->route('admin.rentals.index')
                ->with('error', 'A cancelled rental cannot take payments.');
        }

        if ($rental->isSettled()) {
            return redirect()->route('admin.rentals.show', $rental)
                ->with('error', 'This rental is already fully settled, so there is nothing left to pay.');
        }

        return view('admin.payments.create', [
            'rental' => $rental,
            'balance' => $rental->balanceDue(),
            'methods' => PaymentMethod::options(),
            'mobileMoney' => array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::mobileMoney()),
        ]);
    }

    public function store(StorePaymentRequest $request, Rental $rental, AuditLogger $audit)
    {
        if ($rental->status === RentalStatus::Cancelled) {
            return redirect()->route('admin.rentals.index')
                ->with('error', 'A cancelled rental cannot take payments.');
        }

        $data = $request->validated();

        $payment = $this->payments->record($rental, new PaymentDraft(
            amount: (float) $data['amount'],
            method: PaymentMethod::from($data['payment_method']),
            paidAt: $this->resolvePaidAt($request),
            reference: $data['transaction_reference'] ?? null,
            recordedBy: $request->user()?->id,
            notes: $data['notes'] ?? null,
        ));

        $this->payments->announce($payment, route('admin.payments.receipt', $payment));

        $audit->log('payment.recorded', 'Recorded '.$payment->amount.' TZS '.PaymentMethod::from($data['payment_method'])->label().' payment (receipt '.$payment->receipt_number.') on rental #'.$rental->id.'.', $payment, [
            'amount' => (float) $payment->amount,
            'method' => $data['payment_method'],
            'receipt_number' => $payment->receipt_number,
            'rental_id' => $rental->id,
        ]);

        return redirect()->route('admin.payments.receipt', $payment)
            ->with('status', 'Payment recorded. Receipt '.$payment->receipt_number.' is ready to print.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['rental.customer.user', 'rental.vehicle', 'recordedBy']);

        return view('admin.payments.receipt', [
            'payment' => $payment,
            'remaining' => $payment->rental?->balanceDue() ?? 0,
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $term = $request->input('q');

        return Payment::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('method'), fn ($q) => $q->where('payment_method', $request->input('method')))
            ->when($from, fn ($q) => $q->where(function (mixed $inner) use ($from) {
                $inner->where(fn ($i) => $i->whereNotNull('paid_at')->whereDate('paid_at', '>=', $from))
                    ->orWhere(fn ($i) => $i->whereNull('paid_at')->whereDate('created_at', '>=', $from));
            }))
            ->when($to, fn ($q) => $q->where(function (mixed $inner) use ($to) {
                $inner->where(fn ($i) => $i->whereNotNull('paid_at')->whereDate('paid_at', '<=', $to))
                    ->orWhere(fn ($i) => $i->whereNull('paid_at')->whereDate('created_at', '<=', $to));
            }))
            ->when($term, fn ($q) => $q->where(function (mixed $inner) use ($term) {
                $like = '%'.$term.'%';
                $inner->where('receipt_number', 'like', $like)
                    ->orWhereHas('rental.customer.user', fn ($u) => $u->where('name', 'like', $like));
            }));
    }

    private function resolvePaidAt(Request $request): ?Carbon
    {
        if ($request->has('paid_at') && blank($request->input('paid_at'))) {
            return null;
        }

        if ($request->filled('paid_at')) {
            return Carbon::parse($request->input('paid_at'));
        }

        return now();
    }
}
