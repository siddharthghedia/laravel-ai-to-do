<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\TaskAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskUpdateDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_remove_due_date_by_sending_null()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create(['user_id' => $user->id, 'name' => 'My Tasks']);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Test Task',
            'due_date' => '2023-01-01',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", [
                'due_date' => null,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'due_date' => null,
        ]);
    }

    public function test_behavior_of_empty_string_due_date()
    {
        $user = User::factory()->create();
        $taskList = TaskList::create(['user_id' => $user->id, 'name' => 'My Tasks']);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Test Task',
            'due_date' => '2023-01-01',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", [
                'due_date' => '',
            ]);

        // Expectation: Laravel validation might fail for 'date' rule with empty string, 
        // OR it passes but doesn't update (if ignored). 
        // Let's see what happens.
        if ($response->status() === 422) {
             // If validation fails, it confirms why user thinks it "doesn't work"
             return;
        }

        // If it returns 200, check if it updated
        $task->refresh();
        $this->assertEquals(null, $task->due_date, "Expected due_date to be null if empty string is sent");
    }

    public function test_can_remove_attachment_via_update()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $taskList = TaskList::create(['user_id' => $user->id, 'name' => 'My Tasks']);
        $task = Task::create([
            'task_list_id' => $taskList->id,
            'title' => 'Test Task',
        ]);
        
        $attachment = $task->attachments()->create([
            'file_path' => 'task_attachments/test.jpg',
            'file_name' => 'test.jpg',
        ]);

        // Mock the file existence for deletion check
        Storage::disk('public')->put('task_attachments/test.jpg', 'content');

        $response = $this->actingAs($user)
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Updated Title',
                'remove_attachment_ids' => [$attachment->id],
            ]);

        $response->assertStatus(200);
        
        // Assert attachment is removed from database
        $this->assertDatabaseMissing('task_attachments', [
            'id' => $attachment->id,
        ]);

        // Assert file is deleted from storage
        Storage::disk('public')->assertMissing('task_attachments/test.jpg');
    }
}
