<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\User;
use App\Models\TaskList;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

class TaskReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_are_ordered_by_latest_on_top_by_default()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        
        $task1 = $taskList->tasks()->create(['title' => 'Task 1']);
        $task2 = $taskList->tasks()->create(['title' => 'Task 2']);
        $task3 = $taskList->tasks()->create(['title' => 'Task 3']);

        $response = $this->getJson("/api/tasks?task_list_id={$taskList->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Should be Task 3, Task 2, Task 1 (latest ID on top since positions are all 0)
        $this->assertEquals($task3->id, $data[0]['id']);
        $this->assertEquals($task2->id, $data[1]['id']);
        $this->assertEquals($task1->id, $data[2]['id']);
    }

    public function test_user_can_manually_reorder_tasks()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        
        $task1 = $taskList->tasks()->create(['title' => 'Task 1']);
        $task2 = $taskList->tasks()->create(['title' => 'Task 2']);
        $task3 = $taskList->tasks()->create(['title' => 'Task 3']);

        // Current order (default): [Task 3, Task 2, Task 1]
        // We want new order: [Task 1, Task 3, Task 2]
        $reorderData = [
            'task_ids' => [$task1->id, $task3->id, $task2->id]
        ];

        $response = $this->postJson('/api/tasks/reorder', $reorderData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify index reflects new order (position 0, 1, 2)
        $response = $this->getJson("/api/tasks?task_list_id={$taskList->id}");
        $data = $response->json();

        $this->assertEquals($task1->id, $data[0]['id']);
        $this->assertEquals($task3->id, $data[1]['id']);
        $this->assertEquals($task2->id, $data[2]['id']);
    }

    public function test_user_cannot_reorder_others_tasks()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $taskList1 = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);
        $task1 = $taskList1->tasks()->create(['title' => 'User 1 Task']);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/tasks/reorder', [
            'task_ids' => [$task1->id]
        ]);

        $response->assertStatus(403);
    }
}
