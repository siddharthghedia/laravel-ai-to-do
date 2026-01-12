<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_default_list_on_registration(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(200);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // Verify "My Tasks" list was created
        $this->assertDatabaseHas('task_lists', [
            'name' => 'My Tasks',
            'user_id' => $user->id,
        ]);
    }
}
