<?php

namespace App\Http\Controllers\Customer;

use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $customer = $user->customer;
        $customerId = $customer?->id ?? 0; // 0 matches no bookings when a login has no customer record

        $reservations = Reservation::query()
            ->active()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->orderBy('start_date')
            ->get();

        $rentals = Rental::query()
            ->open()
            ->where('customer_id', $customerId)
            ->with('vehicle')
            ->orderBy('end_date')
            ->get();

        return view('customer.dashboard', [
            'user' => $user,
            'customer' => $customer,
            'reservations' => $reservations,
            'rentals' => $rentals,
            'completedCount' => Rental::query()
                ->where('customer_id', $customerId)
                ->where('status', RentalStatus::Completed)
                ->count(),
            'totalSpend' => (float) Rental::query()
                ->where('customer_id', $customerId)
                ->where('status', RentalStatus::Completed)
                ->sum('total_amount'),
            'alerts' => $user->notifications()->limit(5)->get(),
            'profile' => $this->profileCompleteness($user, $customer),
        ]);
    }

    /**
     * @return array{percent: int, missing: array<int, string>}
     */
    private function profileCompleteness(User $user, ?Customer $customer): array
    {
        $fields = [
            'Phone number' => $user->phone,
            'National ID' => $customer?->national_id,
            'Passport number' => $customer?->passport_no,
            'Driving licence number' => $customer?->driving_license_no,
            'Date of birth' => $customer?->date_of_birth,
            'City' => $customer?->city,
            'Address' => $customer?->address,
            'Emergency contact name' => $customer?->emergency_contact_name,
            'Emergency contact phone' => $customer?->emergency_contact_phone,
        ];

        $missing = array_values(array_keys(array_filter($fields, fn ($value) => blank($value))));
        $filled = count($fields) - count($missing);

        return [
            'percent' => (int) round($filled / count($fields) * 100),
            'missing' => $missing,
        ];
    }
}
