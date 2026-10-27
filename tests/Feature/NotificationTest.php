<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_sees_only_their_own_alerts(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $user->recordAlert('Yours', 'For you', null, 'info');
        $other->recordAlert('Theirs', 'Not for you', null, 'info');

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('For you')
            ->assertDontSee('Not for you');
    }

    public function test_a_user_can_mark_their_own_alert_as_read(): void
    {
        $user = User::factory()->customer()->create();
        $notification = $user->recordAlert('Title', 'Body', null, 'info');

        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->put(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_alerts_belonging_to_another_user_are_not_touchable(): void
    {
        $owner = User::factory()->customer()->create();
        $intruder = User::factory()->customer()->create();
        $notification = $owner->recordAlert('Title', 'Body', null, 'info');

        $this->actingAs($intruder)
            ->put(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guests_cannot_open_the_alert_inbox(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_alerts_are_ordered_newest_first(): void
    {
        $user = User::factory()->customer()->create();
        Notification::query()->insert([
            ['user_id' => $user->id, 'title' => 'Older', 'message' => 'm', 'type' => 'info', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
            ['user_id' => $user->id, 'title' => 'Newer', 'message' => 'm', 'type' => 'info', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame('Newer', $user->notifications()->first()->title);
    }
}
