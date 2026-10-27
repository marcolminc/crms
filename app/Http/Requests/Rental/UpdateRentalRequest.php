<?php

namespace App\Http\Requests\Rental;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date_format:Y-m-d'],
            'additional_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rental = $this->route('rental');
            if (! $rental instanceof Rental) {
                return;
            }

            if ($rental->status === RentalStatus::Cancelled) {
                $validator->errors()->add('end_date', 'A cancelled rental cannot be edited.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = $rental->start_date->copy()->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'))->startOfDay();

            if ($end->lt($start)) {
                $validator->errors()->add('end_date', 'The drop-off date must be on or after the pick-up date.');

                return;
            }

            $days = (int) $start->diffInDays($end) + 1;
            $additional = (float) ($this->input('additional_charges') ?? 0);
            $discount = (float) ($this->input('discount') ?? 0);
            $total = max(0, (float) $rental->rate_per_day * $days + $additional - $discount);

            if ($total < $rental->paidAmount()) {
                $validator->errors()->add(
                    'discount',
                    'The revised total ('.number_format($total, 0).' TZS) is below the '.
                    number_format($rental->paidAmount(), 0).' TZS already paid. Reduce the discount or extra charges.'
                );
            }

            if ($end->toDateString() !== $rental->end_date->toDateString()
                && $this->vehicleBusyFor($rental, $start, $end)) {
                $validator->errors()->add('end_date', 'The vehicle is not free for the extended period.');
            }
        });
    }

    private function vehicleBusyFor(Rental $rental, Carbon $start, Carbon $end): bool
    {
        $excludeReservationId = $rental->reservation_id;

        $reservationBusy = Reservation::query()
            ->active()
            ->where('vehicle_id', $rental->vehicle_id)
            ->when($excludeReservationId, fn ($q) => $q->whereKeyNot($excludeReservationId))
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        $rentalBusy = Rental::query()
            ->open()
            ->where('vehicle_id', $rental->vehicle_id)
            ->whereKeyNot($rental->id)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        return $reservationBusy || $rentalBusy;
    }
}
