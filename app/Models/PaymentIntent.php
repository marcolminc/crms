<?php

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checkout we opened with the gateway, either a hosted page or a mobile money
 * push. Never money on its own: only the ledger in payments carries value.
 */
class PaymentIntent extends Model
{
    use HasFactory;

    private const LIVE_WINDOW_MINUTES = 30;

    protected $fillable = [
        'rental_id',
        'tx_ref',
        'checkout_url',
        'amount',
        'currency',
        'payment_method',
        'status',
        'gateway_tx_id',
        'gateway_ref',
        'payment_id',
        'failure_reason',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentIntentStatus::class,
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public static function liveFor(Rental $rental): ?self
    {
        return static::query()
            ->where('rental_id', $rental->id)
            ->where('created_at', '>=', now()->subMinutes(self::LIVE_WINDOW_MINUTES))
            ->whereIn('status', [PaymentIntentStatus::Pending->value, PaymentIntentStatus::Started->value])
            ->latest('id')
            ->first();
    }

    public static function findByTxRef(string $txRef): ?self
    {
        return static::query()->where('tx_ref', $txRef)->first();
    }

    /**
     * A live mobile money push: the USSD prompt is out on the payer's handset and we
     * are waiting for the callback (or their tap on the waiting page) to confirm it.
     */
    public function isAwaitingPhoneApproval(): bool
    {
        return in_array($this->payment_method, PaymentMethod::mobileMoney(), true)
            && $this->status === PaymentIntentStatus::Started
            && $this->payment_id === null;
    }

    /**
     * Claim an intent for settlement. Only one caller can win, across processes.
     */
    public static function claim(int $id, array $attributes): bool
    {
        return static::query()
            ->whereKey($id)
            ->whereNull('payment_id')
            ->whereNull('gateway_ref')
            ->update($attributes) === 1;
    }
}
