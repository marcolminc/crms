<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raw AzamPay callback, stored exactly as it arrived. Append-only in
 * spirit: rows are written by the webhook handler and only the processing
 * flags are ever updated.
 */
class PaymentGatewayEvent extends Model
{
    protected $fillable = [
        'payment_intent_id',
        'gateway_ref',
        'event_type',
        'raw_payload',
        'signature_valid',
        'processed',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'signature_valid' => 'boolean',
            'processed' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
