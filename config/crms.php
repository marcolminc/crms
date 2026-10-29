<?php

/*
|--------------------------------------------------------------------------
| CRMS business rules
|--------------------------------------------------------------------------
| Tunable thresholds for the operational workflows. Kept in one place so the
| money-sensitive limits are visible and easy to adjust per branch.
*/

return [

    /*
    | UC14: a discount or damage/additional charge at or above this amount
    | (TZS) is applied straight away for the customer's benefit but is also
    | raised to a branch manager for formal sign-off; a manager may reject it,
    | which reverses the charge. Below the threshold the charge stands on its
    | own with no approval round-trip.
    */
    'approval_threshold' => (float) env('CRMS_APPROVAL_THRESHOLD', 200000),

    /*
    | FR13: VAT rate (Tanzania standard rate) applied to taxable hire revenue
    | in the tax report. Rental totals are treated as VAT-inclusive, so the
    | output tax carved out of a gross figure is gross * rate / (1 + rate).
    */
    'vat_rate' => (float) env('CRMS_VAT_RATE', 0.18),

    /*
    | FR15: the optional office driver (chauffeur) is priced as a flat charge
    | per hire day. It is added to a reservation quote the moment the customer
    | asks to be driven, and becomes the rental's driver_fee when a driver is
    | actually assigned (UC23).
    */
    'driver_rate_per_day' => (float) env('CRMS_DRIVER_RATE_PER_DAY', 40000),

];
