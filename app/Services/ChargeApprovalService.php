<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ChargeApproval;
use App\Models\Rental;

/**
 * UC14: decides when a material discount or damage/additional charge must be
 * escalated to a branch manager for sign-off. The charge itself is applied to
 * the rental first (the customer sees the discounted total immediately); this
 * service only records the review request and alerts the managers. Small
 * charges below the configured threshold stand on their own.
 */
final class ChargeApprovalService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function threshold(): float
    {
        return (float) config('crms.approval_threshold');
    }

    /**
     * Raise a pending approval when $amount meets the threshold; otherwise do
     * nothing and let the charge stand. Returns the request, or null.
     */
    public function maybeRequest(Rental $rental, ApprovalType $type, float $amount, ?string $reason): ?ChargeApproval
    {
        if ($amount < $this->threshold()) {
            return null;
        }

        $approval = ChargeApproval::create([
            'rental_id' => $rental->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'status' => ApprovalStatus::Pending,
            'requested_by' => auth()->id(),
        ]);

        $this->audit->log(
            'approval.requested',
            $type->label().' of '.number_format($amount, 0).' TZS on rental #'.$rental->id.' queued for manager approval.',
            $approval,
            ['type' => $type->value, 'amount' => $amount],
        );

        $managers = $this->notifications->branchManagers();
        if ($managers !== []) {
            $this->notifications->forUsers(
                $managers,
                $type === ApprovalType::Discount ? 'Discount needs approval' : $type->label().' needs approval',
                $type === ApprovalType::Discount
                    ? 'A '.number_format($amount, 0).' TZS discount was applied to rental #'.$rental->id.' and needs your review.'
                    : 'A '.number_format($amount, 0).' TZS '.$type->label().' was applied to rental #'.$rental->id.' and needs your review.',
                route('admin.approvals.index'),
                'charge_approval_requested',
            );
        }

        return $approval;
    }
}
