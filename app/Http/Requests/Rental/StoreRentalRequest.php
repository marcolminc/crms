<?php

namespace App\Http\Requests\Rental;

use App\Enums\FuelLevel;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'reservation' => ['nullable', 'integer', Rule::exists('reservations', 'id')],
            'customer' => ['required', 'integer', Rule::exists('customers', 'id')],
            'vehicle' => ['required', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
            'additional_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Handover record: what the car looked like when it drove away.
            'odometer_at_pickup' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'fuel_at_pickup' => ['nullable', 'string', Rule::in(array_keys(FuelLevel::options()))],
            'pickup_photos' => ['nullable', 'array'],
        ];

        foreach (array_keys(Vehicle::SLOTS) as $slot) {
            $rules["pickup_photos.$slot"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [];
        foreach (Vehicle::SLOTS as $slot => $label) {
            $messages["pickup_photos.$slot.image"] = "The {$label} pickup photo must be an image file.";
            $messages["pickup_photos.$slot.mimes"] = "The {$label} pickup photo must be a JPG, PNG or WebP file.";
            $messages["pickup_photos.$slot.max"] = "The {$label} pickup photo must be 4 MB or smaller.";
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'))->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay();

            if ($start->lt(Carbon::today())) {
                $validator->errors()->add('start_date', 'The rental cannot start in the past.');
            }

            $vehicle = Vehicle::query()->find($this->integer('vehicle'));
            if (! $vehicle || ! in_array($vehicle->status, [VehicleStatus::Available, VehicleStatus::Reserved], true)) {
                $validator->errors()->add('vehicle', 'Only Available or Reserved vehicles can be hired out.');
            }

            if ($vehicle && $this->filled('odometer_at_pickup')
                && $vehicle->mileage !== null
                && (int) $this->input('odometer_at_pickup') < (int) $vehicle->mileage) {
                $validator->errors()->add(
                    'odometer_at_pickup',
                    'Pickup odometer cannot be lower than the vehicle\'s recorded mileage ('
                    .number_format((int) $vehicle->mileage).' km).'
                );
            }

            $excludeReservationId = null;

            if ($this->filled('reservation')) {
                $reservation = Reservation::query()->with('customer')->find($this->integer('reservation'));

                if (! $reservation) {
                    $validator->errors()->add('reservation', 'The selected reservation no longer exists.');

                    return;
                }

                $usable = $reservation->status === ReservationStatus::Confirmed
                    && $reservation->rental_id === null
                    && $reservation->customer_id !== null
                    && $reservation->end_date->gte(Carbon::today());

                if (! $usable) {
                    $validator->errors()->add('reservation', 'That reservation is not confirmed, already converted, or has already ended.');

                    return;
                }

                if ($this->integer('customer') !== $reservation->customer_id) {
                    $validator->errors()->add('customer', 'The customer must match the reservation being converted.');
                }

                if ($start->lt($reservation->start_date->copy()->startOfDay())) {
                    $validator->errors()->add('start_date', 'The rental start cannot be before the reservation start date.');
                }

                if ($vehicle && $reservation->vehicle_id !== $vehicle->id) {
                    $validator->errors()->add('vehicle', 'The vehicle must match the reservation being converted.');
                }

                $excludeReservationId = $reservation->id;
            }

            if ($vehicle
                && ! $validator->errors()->hasAny(['vehicle', 'start_date', 'end_date'])
                && $this->vehicleBusyFor($vehicle, $start, $end, $excludeReservationId)) {
                $validator->errors()->add('end_date', 'That vehicle is not free between the selected dates.');
            }
        });
    }

    private function vehicleBusyFor(Vehicle $vehicle, Carbon $start, Carbon $end, ?int $excludeReservationId): bool
    {
        $reservationBusy = Reservation::query()
            ->active()
            ->where('vehicle_id', $vehicle->id)
            ->when($excludeReservationId, fn ($q) => $q->whereKeyNot($excludeReservationId))
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        $rentalBusy = Rental::query()
            ->open()
            ->where('vehicle_id', $vehicle->id)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        $maintenanceBusy = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start->toDateString()))
            ->exists();

        return $reservationBusy || $rentalBusy || $maintenanceBusy;
    }
}
