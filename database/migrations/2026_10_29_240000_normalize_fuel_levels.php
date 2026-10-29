<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fuel is now a fixed set of levels (full / 3-4 / 1-2 / 1-4) backed by an
     * enum, so hand-typed text from the old free-text field is folded onto the
     * closest valid value before the cast goes live. Anything unrecognisable
     * becomes null rather than crashing the model when it is later read.
     */
    public function up(): void
    {
        $map = $this->normalizer();

        foreach (['rentals' => 'fuel_at_pickup', 'vehicle_returns' => 'fuel_level'] as $table => $column) {
            $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);
            foreach ($rows as $row) {
                $value = $map((string) $row->{$column});
                if ($value !== $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $value]);
                }
            }
        }
    }

    public function down(): void
    {
        // Values only ever narrowed to the canonical set; nothing to restore.
    }

    /**
     * @return closure(string): ?string
     */
    private function normalizer(): Closure
    {
        return function (string $raw): ?string {
            $needle = strtolower(trim($raw));

            if ($needle === '') {
                return null;
            }

            // Order matters: "three-quarter" must win over "quarter".
            if (str_contains($needle, 'three') || str_contains($needle, '3/4') || str_contains($needle, '75')) {
                return 'three_quarter';
            }
            if (str_contains($needle, 'full') || $needle === '1' || str_contains($needle, '100')) {
                return 'full';
            }
            if (str_contains($needle, 'half') || str_contains($needle, '1/2') || str_contains($needle, '50')) {
                return 'half';
            }
            if (str_contains($needle, 'quarter') || str_contains($needle, '1/4') || str_contains($needle, '25')) {
                return 'quarter';
            }

            return in_array($needle, ['full', 'three_quarter', 'half', 'quarter'], true) ? $needle : null;
        };
    }
};
