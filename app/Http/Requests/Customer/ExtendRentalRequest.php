<?php

namespace App\Http\Requests\Customer;

use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * UC10: a customer asking to keep the vehicle beyond the current drop-off.
 */
final class ExtendRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'end_date' => 'new drop-off date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('end_date')) {
                return;
            }

            /** @var Rental $rental */
            $rental = $this->route('rental');
            $newEnd = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay();

            if ($newEnd->lte($rental->end_date->copy()->startOfDay())) {
                $validator->errors()->add('end_date', 'The new drop-off date must be later than the current one.');

                return;
            }

            if ((int) $rental->start_date->diffInDays($newEnd) + 1 > 90) {
                $validator->errors()->add('end_date', 'An extended hire cannot be longer than 90 days in total.');

                return;
            }

            if ($rental->extensionWouldConflict($newEnd)) {
                $validator->errors()->add(
                    'end_date',
                    'This vehicle is already booked by someone else in the period you asked for. Please choose an earlier drop-off.'
                );
            }
        });
    }
}
