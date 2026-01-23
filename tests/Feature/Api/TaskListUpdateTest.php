<?php

namespace Tests\Feature\Api;

use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskListUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_task_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Original Name', 'user_id' => $user->id]);

        $response = $this->putJson("/api/task-lists/{$taskList->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Name');

        $this->assertDatabaseHas('task_lists', [
            'id' => $taskList->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_cannot_update_others_task_list()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $taskList = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->putJson("/api/task-lists/{$taskList->id}", [
            'name' => 'Stolen Goal',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_lists', [
            'id' => $taskList->id,
            'name' => 'User 1 List',
        ]);
    }

    public function test_update_task_list_requires_name()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Original Name', 'user_id' => $user->id]);

        $response = $this->putJson("/api/task-lists/{$taskList->id}", [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
