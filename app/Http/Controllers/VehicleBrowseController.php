<?php

namespace App\Http\Controllers;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Http\Request;

final class VehicleBrowseController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::query()->search($this->text($request->query('q')));

        $type = $request->query('type');
        $validType = is_string($type) && array_key_exists($type, VehicleType::options());
        if ($validType) {
            $query->where('type', $type);
        }

        $seats = $request->query('seats');
        $validSeats = is_scalar($seats) && is_numeric($seats) && (int) $seats > 0;
        if ($validSeats) {
            $query->where('seats', '>=', (int) $seats);
        }

        $maxRate = $request->query('max_rate');
        $validMaxRate = is_scalar($maxRate) && is_numeric($maxRate) && (float) $maxRate > 0;
        if ($validMaxRate) {
            $query->where('rate_per_day', '<=', (float) $maxRate);
        }

        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        if ($from !== null && $to !== null) {
            $query->availableBetween($from, $to);
        } else {
            $query->where('status', VehicleStatus::Available);
        }

        $vehicles = $query->orderBy('rate_per_day')->paginate(12)->withQueryString();

        $filters = [
            'q' => $this->text($request->query('q')),
            'type' => $validType ? $type : '',
            'seats' => $validSeats ? (string) (int) $seats : '',
            'max_rate' => $validMaxRate ? (string) (float) $maxRate : '',
            'from' => $from ?? '',
            'to' => $to ?? '',
        ];

        // The infinite-scroll watcher asks for one page at a time and gets the
        // ready-rendered card grid back; without JS the classic links still work.
        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('vehicles._partials.cards', [
                    'vehicles' => $vehicles,
                    'filters' => $filters,
                ])->toHtml(),
                'next' => $vehicles->hasMorePages()
                    ? $vehicles->appends(['partial' => 1])->nextPageUrl()
                    : null,
                'shown' => $vehicles->firstItem() === null ? 0 : $vehicles->count() + $vehicles->firstItem() - 1,
                'total' => $vehicles->total(),
            ]);
        }

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'types' => VehicleType::options(),
            'nextPageUrl' => $vehicles->hasMorePages() ? $vehicles->appends(['partial' => 1])->nextPageUrl() : null,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));
        $hasWindow = $from !== null && $to !== null;
        $isFree = $hasWindow ? $vehicle->isFreeBetween($from, $to) : null;

        $user = $request->user();
        $canBook = $user !== null && $user->isCustomer();

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'hasWindow' => $hasWindow,
            'isFree' => $isFree,
            'canBook' => $canBook,
        ]);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
