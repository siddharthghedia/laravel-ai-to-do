<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\TaskList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_soft_delete_own_task_list()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create([
            'name' => 'My List',
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/task-lists/{$taskList->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task list soft deleted successfully',
            ]);

        $this->assertSoftDeleted('task_lists', [
            'id' => $taskList->id,
        ]);
    }

    public function test_user_cannot_delete_someone_elses_task_list()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $taskList = TaskList::create([
            'name' => 'User 1 List',
            'user_id' => $user1->id,
        ]);

        $token = $user2->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/task-lists/{$taskList->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_lists', [
            'id' => $taskList->id,
            'deleted_at' => null,
        ]);
    }

    public function test_soft_deleted_task_list_is_not_returned_in_index()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create([
            'name' => 'Deleted List',
            'user_id' => $user->id,
        ]);
        $taskList->delete();

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/task-lists');

        $response->assertStatus(200)
            ->assertJsonMissing([
                'id' => $taskList->id,
            ]);
    }
}
