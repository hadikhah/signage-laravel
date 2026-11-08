<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testing-key',
            'broadcasting.connections.reverb.secret' => 'testing-secret',
            'broadcasting.connections.reverb.app_id' => 'testing-app',
        ]);

        // The suite boots with the null broadcaster, which has its own channel registry.
        require base_path('routes/channels.php');
    }

    public function test_guests_cannot_authorize_private_channels(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.1',
        ])->assertForbidden();
    }

    public function test_users_can_authorize_their_own_private_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_users_cannot_authorize_another_users_private_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ])->assertForbidden();
    }
}
