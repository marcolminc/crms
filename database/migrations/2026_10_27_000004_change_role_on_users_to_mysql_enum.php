<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tighten users.role from a free varchar to a closed set of role values.
 *
 * On MySQL this becomes a native ENUM built from the App\Enums\UserRole
 * cases on purpose: the schema and the PHP enum cannot drift apart, and a
 * bad role can no longer be written even by raw SQL (under sql_mode strict
 * the server rejects it). On other drivers (e.g. the sqlite test database)
 * the column is rebuilt as Laravel's enum(), which emits a CHECK
 * constraint — the strictness is equivalent for our purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = UserRole::values();

        if (DB::getDriverName() === 'mysql') {
            $list = "'".implode("','", $values)."'";
            // MODIFY keeps the existing users_role_index on the column.
            DB::statement("ALTER TABLE users MODIFY role ENUM({$list}) NOT NULL DEFAULT 'customer'");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($values) {
            // change() recreates the table keeping every other definition
            // intact — dropping and re-adding the column would silently
            // strip AUTOINCREMENT from the primary key on sqlite.
            $table->enum('role', $values)->default('customer')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(255) NOT NULL DEFAULT 'customer'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 255)->default('customer')->change();
        });
    }
};
