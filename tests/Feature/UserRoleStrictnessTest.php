<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * roles are a closed set: the same values are enforced in PHP (UserRole),
 * on the wire (form requests), on the route (middleware) and in the database
 * (native MySQL ENUM / CHECK constraint on the test driver).
 */
class UserRoleStrictnessTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('everyRole')]
    public function test_the_database_accepts_each_defined_role(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $stored = DB::table('users')->where('id', $user->id)->value('role');
        $this->assertSame($role->value, $stored);
        $this->assertSame($role, $user->fresh()->role);
    }

    /**
     * @return array<string, array<int, UserRole>>
     */
    public static function everyRole(): array
    {
        return array_combine(
            array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
            array_map(fn (UserRole $r) => [$r], UserRole::cases())
        );
    }

    public function test_the_column_is_constrained_to_the_role_values(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $definition = strtolower(
                DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'")->Type ?? ''
            );
            $this->assertStringStartsWith('enum(', $definition, 'role must be a native MySQL ENUM.');
            foreach (UserRole::values() as $value) {
                $this->assertStringContainsString("'{$value}'", $definition);
            }

            return;
        }

        // On the sqlite test driver the enum() column becomes a CHECK
        // constraint; assert the equivalent by writing through it.
        $this->expectException(QueryException::class);
        $this->insertRawRole('super_admin');
    }

    public function test_the_database_rejects_a_role_that_is_not_defined(): void
    {
        $this->expectException(QueryException::class);
        $this->insertRawRole('not-a-real-role');
    }

    public function test_the_database_rejects_an_empty_role(): void
    {
        $this->expectException(QueryException::class);
        $this->insertRawRole('');
    }

    private function insertRawRole(string $role): void
    {
        DB::table('users')->insert([
            'name' => 'Probe',
            'email' => 'probe@example.test',
            'password' => bcrypt('secret-password'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_creating_a_staff_account_with_an_unknown_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Whiff',
            'email' => 'whiff@example.test',
            'role' => 'super_admin',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'whiff@example.test']);
    }

    public function test_a_valid_role_passes_validation_and_is_stored(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nuru',
            'email' => 'nuru@example.test',
            'role' => UserRole::Finance->value,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            UserRole::Finance,
            User::where('email', 'nuru@example.test')->firstOrFail()->role
        );
    }

    public function test_the_guard_middleware_rejects_an_unknown_route_role(): void
    {
        // A typo in a route's role: parameter is a developer bug and must
        // surface loudly rather than quietly granting or denying access.
        $middleware = new EnsureUserHasRole;
        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(fn () => User::factory()->admin()->create());

        $this->expectException(\ValueError::class);
        $middleware->handle($request, fn () => response('ok'), 'fleet-manger');
    }
}
