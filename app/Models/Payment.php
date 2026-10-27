<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'receipt_number',
        'type',
        'parent_payment_id',
        'amount',
        'payment_method',
        'status',
        'transaction_reference',
        'recorded_by',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'type' => PaymentType::class,
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }

    /**
     * How much of this payment has not been given back yet.
     */
    public function refundableAmount(): float
    {
        if ($this->status === PaymentStatus::Refunded || ! $this->type->isCollected()) {
            return 0.0;
        }

        $refunded = (float) $this->refunds()
            ->whereNotNull('paid_at')
            ->where('type', PaymentType::Refund->value)
            ->sum('amount');

        return round(max(0, (float) $this->amount - $refunded), 2);
    }

    public function requiresTransactionReference(): bool
    {
        return in_array($this->payment_method, PaymentMethod::mobileMoney(), true);
    }

    public static function nextReceiptNumber(int $offset = 0): string
    {
        $sequence = static::whereDate('created_at', today())->count() + 1 + $offset;

        return 'RC-'.today()->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
