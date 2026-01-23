<?php

namespace Tests\Feature\Api;

use App\Models\TaskList;
use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_search_tasks_by_title()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $taskList->tasks()->create(['title' => 'Buy milk', 'description' => 'Go to store']);
        $taskList->tasks()->create(['title' => 'Clean room', 'description' => 'Vacuum the floor']);

        $response = $this->getJson('/api/tasks/search?q=milk');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Buy milk');
    }

    public function test_user_can_search_tasks_by_description()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $taskList->tasks()->create(['title' => 'Buy milk', 'description' => 'Go to store']);
        $taskList->tasks()->create(['title' => 'Clean room', 'description' => 'Vacuum the floor']);

        $response = $this->getJson('/api/tasks/search?q=vacuum');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Clean room');
    }

    public function test_search_only_returns_own_tasks()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $taskList1 = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);
        $taskList1->tasks()->create(['title' => 'User 1 Secret Task']);

        $taskList2 = TaskList::create(['name' => 'User 2 List', 'user_id' => $user2->id]);
        $taskList2->tasks()->create(['title' => 'User 2 Open Task']);

        Sanctum::actingAs($user2);

        $response = $this->getJson('/api/tasks/search?q=Secret');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_search_results_are_paginated()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        
        for ($i = 1; $i <= 20; $i++) {
            $taskList->tasks()->create(['title' => "Special Task $i"]);
        }

        $response = $this->getJson('/api/tasks/search?q=Special&per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('total', 20);
    }

    public function test_user_can_search_tasks_filtered_by_list()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList1 = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $taskList2 = TaskList::create(['name' => 'Personal', 'user_id' => $user->id]);

        $taskList1->tasks()->create(['title' => 'Buy milk', 'description' => 'Go to store']);
        $taskList2->tasks()->create(['title' => 'Buy bread', 'description' => 'Go to bakery']);

        // Search for 'Buy' in taskList1
        $response = $this->getJson("/api/tasks/search?q=Buy&task_list_id={$taskList1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Buy milk');
    }

    public function test_user_cannot_search_filtered_by_others_list()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $taskList1 = TaskList::create(['name' => 'User 1 List', 'user_id' => $user1->id]);
        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/tasks/search?q=test&task_list_id={$taskList1->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_list_id']);
    }

    public function test_search_query_is_required()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tasks/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_results_can_be_sorted()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskList = TaskList::create(['name' => 'Work', 'user_id' => $user->id]);
        $taskList->tasks()->create(['title' => 'Alpha Task', 'due_date' => '2026-01-21']);
        $taskList->tasks()->create(['title' => 'Beta Task', 'due_date' => '2026-01-20']);

        // Search for 'Task' and sort by title desc
        $response = $this->getJson('/api/tasks/search?q=Task&sort_by=title&sort_order=desc');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Beta Task')
            ->assertJsonPath('data.1.title', 'Alpha Task');

        // Search for 'Task' and sort by due_date asc
        $response = $this->getJson('/api/tasks/search?q=Task&sort_by=due_date&sort_order=asc');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Beta Task')
            ->assertJsonPath('data.1.title', 'Alpha Task');
    }
}
