<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser', $user->username);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'sudahada']);
        $user = User::factory()->create(['username' => 'punyaku']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => 'sudahada',
        ])->assertSessionHasErrors('username');
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile')->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
