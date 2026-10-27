<?php

namespace App\Console\Commands;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Services\OnlinePaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Design 5.5 / UC22: a customer can approve a prompt and the callback can still
 * never reach us (network, firewall, gateway hiccup). This scheduler job asks
 * AzamPay directly about every open checkout, settles what really was paid, and
 * closes what has clearly died. The gateway's own answer is the only truth used.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile
        {--grace=5 : Minutes an open checkout waits before the gateway is asked about it}
        {--expire=1440 : Minutes after which an unconfirmed checkout is closed as expired}';

    protected $description = 'Ask AzamPay about payments whose callback has not arrived yet, and expire dead checkouts';

    public function handle(OnlinePaymentService $checkout): int
    {
        if (! $checkout->enabled()) {
            $this->info('AzamPay is not configured; nothing to reconcile.');

            return self::SUCCESS;
        }

        $grace = max(1, (int) $this->option('grace'));
        $expire = max($grace, (int) $this->option('expire'));

        $settled = $declined = $pending = $expired = 0;

        // 1. Anything the gateway can still answer for gets verified through the
        //    normal settlement funnel — the same code path the webhook uses.
        $open = PaymentIntent::query()
            ->whereNull('payment_id')
            ->whereIn('status', [PaymentIntentStatus::Pending, PaymentIntentStatus::Started])
            ->where('created_at', '<=', now()->subMinutes($grace))
            ->orderBy('id')
            ->get();

        foreach ($open as $intent) {
            try {
                $outcome = $checkout->settleFromReturn($intent);
            } catch (Throwable $exception) {
                $this->error("Intent {$intent->id}: ".$exception->getMessage());
                $pending++;

                continue;
            }

            match (true) {
                $outcome === GatewayCaptureOutcome::Recorded,
                $outcome === GatewayCaptureOutcome::AlreadyRecorded => $settled++,
                $outcome === GatewayCaptureOutcome::GatewayUnavailable => $pending++,
                default => $declined++,
            };

            $this->line("Intent {$intent->id} ({$intent->tx_ref}): {$outcome->value}");
        }

        // 2. A checkout that stayed open past the expiry window is closed. Money
        //    that leaves a wallet after that is caught by the webhook's unknown
        //    or mismatch guards, never by silently leaving the intent open.
        $expiredIds = PaymentIntent::query()
            ->whereNull('payment_id')
            ->whereIn('status', [PaymentIntentStatus::Pending, PaymentIntentStatus::Started])
            ->where('created_at', '<=', now()->subMinutes($expire))
            ->pluck('id');

        foreach ($expiredIds as $id) {
            PaymentIntent::query()
                ->whereKey($id)
                ->whereNull('payment_id')
                ->update([
                    'status' => PaymentIntentStatus::Failed,
                    'failure_reason' => GatewayCaptureOutcome::Expired->value,
                ]);
            $expired++;
        }

        $this->info("Reconciled: {$settled} settled, {$declined} refused, {$pending} still waiting, {$expired} expired.");

        return self::SUCCESS;
    }
}
