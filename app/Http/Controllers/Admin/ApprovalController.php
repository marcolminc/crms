<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\RejectApprovalRequest;
use App\Models\ChargeApproval;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * UC14: the branch manager's (and administrator's) review queue for material
 * discounts and damage/additional charges. Approving simply signs the charge
 * off; rejecting reverses it on the rental and tells the requester and the
 * customer why. Only managers/admin may reach this screen.
 */
final class ApprovalController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeReviewer($request);

        $approvals = ChargeApproval::query()
            ->with(['rental.vehicle', 'rental.customer.user', 'requester', 'reviewer'])
            ->when($request->filled('status'), function ($q) use ($request) {
                if (array_key_exists($request->input('status'), ApprovalStatus::options())) {
                    $q->where('status', $request->input('status'));
                }
            })
            ->when($request->filled('type'), function ($q) use ($request) {
                if (ApprovalType::tryFrom((string) $request->input('type')) !== null) {
                    $q->where('type', $request->input('type'));
                }
            })
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', ApprovalStatus::Pending->value))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.approvals.index', [
            'approvals' => $approvals,
            'statuses' => ApprovalStatus::options(),
            'types' => ApprovalType::options(),
            'pendingCount' => ChargeApproval::query()->where('status', ApprovalStatus::Pending->value)->count(),
        ]);
    }

    public function approve(Request $request, ChargeApproval $approval)
    {
        $this->authorizeReviewer($request);

        if (! $approval->isPending()) {
            return redirect()->back()->with('error', 'This request has already been reviewed.');
        }

        $approval->update([
            'status' => ApprovalStatus::Approved,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('note'),
        ]);

        $this->audit->log(
            'approval.approved',
            'Approved '.$approval->type->label().' of '.number_format((float) $approval->amount, 0).' TZS on rental #'.$approval->rental_id.'.',
            $approval,
            ['type' => $approval->type->value, 'amount' => (float) $approval->amount],
        );

        $this->notifyRequester($approval, 'Charge approved', $approval->type->label().' of '.number_format((float) $approval->amount, 0).' TZS on rental #'.$approval->rental_id.' has been approved.');

        return redirect()->back()->with('status', 'The charge has been approved.');
    }

    public function reject(RejectApprovalRequest $request, ChargeApproval $approval)
    {
        $this->authorizeReviewer($request);

        if (! $approval->isPending()) {
            return redirect()->back()->with('error', 'This request has already been reviewed.');
        }

        $rental = $approval->rental;

        // Reverse the charge on the rental, keeping the money columns in step.
        if ($rental !== null) {
            if ($approval->type === ApprovalType::Discount) {
                $rental->discount = 0;
            } else {
                $rental->additional_charges = max(0, (float) $rental->additional_charges - (float) $approval->amount);
            }
            $rental->recalculate();
            $rental->save();
        }

        $approval->update([
            'status' => ApprovalStatus::Rejected,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => $request->validated('note'),
        ]);

        $this->audit->log(
            'approval.rejected',
            'Rejected '.$approval->type->label().' of '.number_format((float) $approval->amount, 0).' TZS on rental #'.$approval->rental_id.'. Charge reversed. Reason: '.$request->validated('note'),
            $approval,
            ['type' => $approval->type->value, 'amount' => (float) $approval->amount, 'reason' => $request->validated('note')],
        );

        $this->notifyRequester($approval, 'Charge rejected', $approval->type->label().' of '.number_format((float) $approval->amount, 0).' TZS on rental #'.$approval->rental_id.' was rejected and reversed. Reason: '.$request->validated('note'));

        if ($rental !== null) {
            $this->notifications->forCustomer(
                $rental->customer,
                'Rental total adjusted',
                'A '.strtolower($approval->type->label()).' on your rental has been reversed by our manager. '
                    .'Revised total: '.number_format((float) $rental->total_amount, 0).' TZS.',
                route('customer.rentals.index'),
                'rental_total_adjusted',
            );
        }

        return redirect()->back()->with('status', 'The charge has been rejected and reversed.');
    }

    private function authorizeReviewer(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::BranchManager, UserRole::Admin) ?? false,
            403,
            'Only branch managers and administrators can review approvals.'
        );
    }

    private function notifyRequester(ChargeApproval $approval, string $title, string $message): void
    {
        if ($approval->requested_by === null) {
            return;
        }

        $this->notifications->forUsers(
            [$approval->requested_by],
            $title,
            $message,
            route('admin.approvals.index'),
            'charge_approval_reviewed',
        );
    }
}
