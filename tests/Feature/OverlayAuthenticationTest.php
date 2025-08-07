<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_access_token()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/tokens', [
                'name' => 'Test Token',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('overlay_access_tokens', [
            'user_id' => $user->id,
            'name' => 'Test Token',
        ]);
    }

    public function test_invalid_token_cannot_access_overlay()
    {
        $template = OverlayTemplate::factory()->create();

        $response = $this->postJson('/api/overlay/render', [
            'slug' => $template->slug,
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_valid_token_can_access_overlay()
    {
        $user = User::factory()->create();
        $template = OverlayTemplate::factory()->create();

        $tokenData = OverlayAccessToken::generateToken();
        $token = $user->overlayAccessTokens()->create([
            'name' => 'Test Token',
            'token_hash' => $tokenData['hash'],
            'token_prefix' => $tokenData['prefix'],
        ]);

        $response = $this->postJson('/api/overlay/render', [
            'slug' => $template->slug,
            'token' => $tokenData['plain'],
        ]);

        $response->assertStatus(200);
    }
}
