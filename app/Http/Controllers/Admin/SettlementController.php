<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\AuditLogger;
use App\Services\RefundService;
use DomainException;
use Illuminate\Http\Request;

/**
 * FR9: settle a hire that has been over-collected. This happens when a charge
 * is reversed after the customer had already paid it (a manager rejecting a
 * damage or late fee, for example), leaving money kept above the final total.
 * The surplus is paid back through the same AzamPay disbursement rail the
 * refund service uses; the walk stops at the smallest set of collected payments
 * that covers it, so the ledger keeps refunds attached to what they refund.
 */
final class SettlementController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly AuditLogger $audit,
    ) {}

    public function refundForm(Request $request, Rental $rental)
    {
        $this->authorizeSettler($request);

        $rental->load(['customer.user', 'vehicle', 'payments.recordedBy']);

        return view('admin.rentals.refund', [
            'rental' => $rental,
            'surplus' => $this->surplus($rental),
            'refundable' => $this->refundablePayments($rental),
        ]);
    }

    public function refund(Request $request, Rental $rental)
    {
        $this->authorizeSettler($request);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $surplus = $this->surplus($rental);

        if ($surplus <= 0.005) {
            return redirect()->route('admin.rentals.invoice', $rental)
                ->with('error', 'This rental is not owed a refund.');
        }

        if ((float) $validated['amount'] > $surplus + 0.005) {
            return back()->with('error', 'The refund cannot be larger than the amount over-paid ('.number_format($surplus, 0).' TZS).');
        }

        if (in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true)) {
            return back()->with('error', 'Complete the rental before settling a refund.');
        }

        $remaining = (float) $validated['amount'];
        $issued = [];

        try {
            foreach ($this->refundablePayments($rental) as $payment) {
                if ($remaining <= 0.005) {
                    break;
                }

                $portion = round(min($remaining, $payment->refundableAmount()), 2);
                if ($portion <= 0) {
                    continue;
                }

                $issued[] = $this->refunds->refund($payment, $portion, $validated['reason'], $request->user());
                $remaining = round($remaining - $portion, 2);
            }
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($issued === []) {
            return back()->with('error', 'None of the collected payments can be refunded through AzamPay; settle this refund at the office instead.');
        }

        $totalRefunded = round(array_sum(array_map(fn (Payment $refund) => (float) $refund->amount, $issued)), 2);

        $this->audit->log(
            'settlement.refunded',
            'Refunded '.number_format($totalRefunded, 0).' TZS surplus on rental #'.$rental->id.'. Reason: '.$validated['reason'],
            $rental,
            ['amount' => $totalRefunded, 'refunds' => array_map(fn (Payment $r) => $r->id, $issued)],
        );

        $leftover = $remaining > 0.005
            ? ' '.number_format($remaining, 0).' TZS could not be paid out online and must be settled at the office.'
            : '';

        return redirect()->route('admin.rentals.invoice', $rental)
            ->with('status', 'Refund of '.number_format($totalRefunded, 0).' TZS sent through AzamPay.'.$leftover);
    }

    private function surplus(Rental $rental): float
    {
        return round(max(0, $rental->paidAmount() - (float) $rental->total_amount), 2);
    }

    /**
     * Collected payments that still have money AzamPay can push back out, oldest
     * first, so refunds map onto the earliest untouched charges.
     */
    private function refundablePayments(Rental $rental)
    {
        return $rental->payments()
            ->where('type', PaymentType::Payment->value)
            ->whereNotNull('paid_at')
            ->where('status', '!=', PaymentStatus::Refunded->value)
            ->orderBy('paid_at')
            ->get()
            ->filter(fn (Payment $payment) => $this->refunds->refundsOnline($payment) && $payment->refundableAmount() > 0);
    }

    private function authorizeSettler(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::Finance, UserRole::BranchManager, UserRole::Admin) ?? false,
            403,
            'Only finance, managers and administrators can settle refunds.'
        );
    }
}
