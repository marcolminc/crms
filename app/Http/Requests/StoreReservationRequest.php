<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vehicle' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle.required' => 'Select the vehicle you want to book.',
            'vehicle.exists' => 'That vehicle is no longer available for booking.',
            'start_date.required' => 'Select the pick-up date.',
            'start_date.date_format' => 'Enter the pick-up date in the correct format.',
            'end_date.required' => 'Select the drop-off date.',
            'end_date.date_format' => 'Enter the drop-off date in the correct format.',
            'end_date.after_or_equal' => 'The drop-off date must be the same day as, or later than, the pick-up date.',
            'notes.max' => 'Please keep your notes within 2000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle' => 'vehicle',
            'start_date' => 'pick-up date',
            'end_date' => 'drop-off date',
            'notes' => 'notes',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['vehicle', 'start_date', 'end_date'])) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'))->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay();

            if ($start->lt(Carbon::today())) {
                $validator->errors()->add('start_date', 'The pick-up date cannot be in the past.');

                return;
            }

            if ((int) $start->diffInDays($end) + 1 > 90) {
                $validator->errors()->add('end_date', 'A booking cannot be longer than 90 days.');

                return;
            }

            $vehicle = Vehicle::query()->find($this->integer('vehicle'));

            if (! $vehicle || ! $vehicle->isFreeBetween($start->toDateString(), $end->toDateString())) {
                $validator->errors()->add(
                    'end_date',
                    'That vehicle is not free between the selected dates. Please choose a different period.'
                );
            }
        });
    }
}
