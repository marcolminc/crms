<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns users.role with the actor catalogue of the design document (§2):
 *
 *   rental_staff   -> counter_agent   (Counter Agent)
 *   manager        -> branch_manager  (Branch Manager)
 *   fleet_manager  -> fleet_staff     (Fleet/Maintenance Staff)
 *   accountant     -> finance         (Finance/Accountant)
 *   admin, customer unchanged; driver (Chauffeur) is new.
 *
 * The ENUM is rebuilt in three steps — widen to the union of old and new
 * values, remap the rows, then narrow to the final set — so no row ever
 * holds a value outside the column's constraint.
 */
return new class extends Migration
{
    /** old value => new value */
    private const MAPPING = [
        'rental_staff' => 'counter_agent',
        'manager' => 'branch_manager',
        'fleet_manager' => 'fleet_staff',
        'accountant' => 'finance',
    ];

    private const OLD = ['admin', 'fleet_manager', 'rental_staff', 'accountant', 'manager', 'customer'];
    private const FINAL = ['admin', 'counter_agent', 'branch_manager', 'fleet_staff', 'finance', 'driver', 'customer'];

    public function up(): void
    {
        $this->setRoleValues(array_merge(self::OLD, self::FINAL));
        $this->remap(self::MAPPING);
        $this->setRoleValues(self::FINAL);
    }

    public function down(): void
    {
        $this->setRoleValues(array_merge(self::OLD, self::FINAL));
        $this->remap(array_flip(self::MAPPING));
        $this->setRoleValues(self::OLD);
    }

    /**
     * Redefine the role column to accept exactly $values (NOT NULL,
     * default customer). Rows must already fit the new set.
     */
    private function setRoleValues(array $values): void
    {
        $values = array_values(array_unique($values));

        if (DB::getDriverName() === 'mysql') {
            $list = "'".implode("','", $values)."'";
            DB::statement("ALTER TABLE users MODIFY role ENUM({$list}) NOT NULL DEFAULT 'customer'");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($values) {
            $table->enum('role', $values)->default('customer')->change();
        });
    }

    /** @param array<string, string> $mapping */
    private function remap(array $mapping): void
    {
        foreach ($mapping as $from => $to) {
            DB::table('users')->where('role', $from)->update(['role' => $to]);
        }
    }
};
