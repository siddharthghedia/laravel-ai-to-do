<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaskCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_own_task()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create([
            'user_id' => $user->id,
            'name' => 'My Tasks'
        ]);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Test Task',
            'status' => 'open'
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tasks/{$task->id}/complete");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'closed'
        ]);
    }

    public function test_user_cannot_complete_others_task()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $taskList = TaskList::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Tasks'
        ]);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Other Task',
            'status' => 'open'
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tasks/{$task->id}/complete");

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'open'
        ]);
    }

    public function test_user_can_reopen_own_task()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create([
            'user_id' => $user->id,
            'name' => 'My Tasks'
        ]);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Test Task',
            'status' => 'closed'
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tasks/{$task->id}/open");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'open'
        ]);
    }
}
