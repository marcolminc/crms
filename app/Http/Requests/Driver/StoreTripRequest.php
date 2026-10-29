<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * UC24: a chauffeur's daily trip log for a rental they are driving.
 */
final class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() ?? false;
    }

    public function rules(): array
    {
        return [
            'trip_date' => ['required', 'date_format:Y-m-d'],
            'started_at' => ['nullable', 'date_format:H:i'],
            'ended_at' => ['nullable', 'date_format:H:i', 'after:started_at'],
            'odometer_start' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'odometer_end' => ['nullable', 'integer', 'gte:odometer_start', 'max:99999999'],
            'incident_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_date.required' => 'Select the day this trip covers.',
            'ended_at.after' => 'The end time must be later than the start time.',
            'odometer_end.gte' => 'The closing odometer cannot be lower than the opening reading.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['trip_date'])) {
                return;
            }

            $rental = $this->route('rental');

            if ($rental !== null
                && $this->filled('trip_date')
                && \Carbon\Carbon::createFromFormat('Y-m-d', (string) $this->input('trip_date'))->startOfDay()
                    ->gt($rental->end_date->copy()->addDays(7)->startOfDay())) {
                $validator->errors()->add('trip_date', 'This trip is too far outside the rental period to log here.');
            }
        });
    }
}
