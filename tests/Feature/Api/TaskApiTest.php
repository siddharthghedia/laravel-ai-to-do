<?php

namespace Tests\Feature\Api;

use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_task_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/task-lists', [
            'name' => 'My Tasks',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'My Tasks');

        $this->assertDatabaseHas('task_lists', ['name' => 'My Tasks']);
    }

    public function test_user_can_create_task_in_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);

        $response = $this->postJson('/api/tasks', [
            'task_list_id' => $taskList->id,
            'title' => 'Design Meeting',
            'description' => 'Discuss project plans',
            'due_date' => '2026-01-13',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'frequency' => 'daily',
            'images' => [
                \Illuminate\Http\UploadedFile::fake()->image('design1.jpg'),
                \Illuminate\Http\UploadedFile::fake()->image('design2.jpg'),
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Design Meeting')
            ->assertJsonPath('start_time', '10:00')
            ->assertJsonPath('end_time', '11:00')
            ->assertJsonCount(2, 'attachments')
            ->assertJsonStructure([
                'attachments' => [
                    '*' => ['url', 'file_name']
                ]
            ]);

        $attachmentUrl1 = $response->json('attachments.0.url');
        $attachmentUrl2 = $response->json('attachments.1.url');
        $this->assertStringStartsWith('http', $attachmentUrl1);
        $this->assertStringStartsWith('http', $attachmentUrl2);

        $this->assertDatabaseHas('tasks', ['title' => 'Design Meeting']);
        $this->assertDatabaseHas('task_attachments', ['file_name' => 'design1.jpg']);
    }

    public function test_user_can_get_tasks_for_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $task = $taskList->tasks()->create(['title' => 'Task 1']);
        $task->attachments()->create(['file_path' => 'tasks/image.jpg', 'file_name' => 'image.jpg']);

        $response = $this->getJson("/api/tasks?task_list_id={$taskList->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.attachments.0.file_name', 'image.jpg')
            ->assertJsonStructure([
                '*' => [
                    'attachments' => [
                        '*' => ['url', 'file_name']
                    ]
                ]
            ]);
    }

    public function test_user_can_archive_task()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $task = $taskList->tasks()->create(['title' => 'To Archive']);

        $response = $this->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_user_can_get_archived_tasks()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $task = $taskList->tasks()->create(['title' => 'Archived Task']);
        $task->delete();

        $response = $this->getJson("/api/tasks?archived=true");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Archived Task');
    }

    public function test_user_can_restore_archived_task()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $task = $taskList->tasks()->create(['title' => 'To Restore']);
        $task->delete();

        $response = $this->postJson("/api/tasks/{$task->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('title', 'To Restore');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_create_task_in_others_list()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1List = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/tasks', [
            'task_list_id' => $user1List->id,
            'title' => 'Stolen Task',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_list_id']);
    }

    public function test_user_cannot_access_others_task()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $user1List = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);
        $user1Task = $user1List->tasks()->create(['title' => 'User 1 Task']);

        Sanctum::actingAs($user2);

        // Test Show
        $this->getJson("/api/tasks/{$user1Task->id}")->assertStatus(403);

        // Test Update
        $this->putJson("/api/tasks/{$user1Task->id}", ['title' => 'Hacked'])
            ->assertStatus(403);

        // Test Delete
        $this->deleteJson("/api/tasks/{$user1Task->id}")->assertStatus(403);
    }

    public function test_index_only_shows_own_lists_and_tasks()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);
        $user2List = TaskList::create(['name' => 'User 2 List', 'user_id' => $user2->id]);
        $user2List->tasks()->create(['title' => 'User 2 Task']);

        Sanctum::actingAs($user2);

        // Test Lists Index
        $this->getJson('/api/task-lists')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'User 2 List');

        // Test Tasks Index
        $this->getJson('/api/tasks')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'User 2 Task');
    }
}
