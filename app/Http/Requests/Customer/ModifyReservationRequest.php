<?php

namespace App\Http\Requests\Customer;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * UC07: a customer changing the dates on their own open reservation.
 */
final class ModifyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'pick-up date',
            'end_date' => 'drop-off date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'))->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay();

            if ($start->lt(Carbon::today())) {
                $validator->errors()->add('start_date', 'The new pick-up date cannot be in the past.');

                return;
            }

            if ((int) $start->diffInDays($end) + 1 > 90) {
                $validator->errors()->add('end_date', 'A booking cannot be longer than 90 days.');
            }
        });
    }
}
