<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Task::with('attachments')
            ->whereHas('taskList', function ($q) {
                $q->where('user_id', auth()->id());
            });

        if ($request->has('task_list_id')) {
            $query->where('task_list_id', $request->task_list_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('archived') && $request->archived == 'true') {
            $query->onlyTrashed();
        }

        $tasks = $query->get();

        return response()->json($tasks);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'task_list_id' => [
                'required',
                'exists:task_lists,id',
                function ($attribute, $value, $fail) {
                    if (!TaskList::where('id', $value)->where('user_id', auth()->id())->exists()) {
                        $fail('The selected task list does not belong to you.');
                    }
                },
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'frequency' => 'nullable|in:none,daily,weekly,monthly,yearly',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $task = Task::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('task_attachments', 'public');
                $task->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $image->getClientOriginalName(),
                ]);
            }
        }

        return response()->json($task->load('attachments'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        if ($task->taskList->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($task->load('attachments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        if ($task->taskList->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'task_list_id' => [
                'sometimes',
                'exists:task_lists,id',
                function ($attribute, $value, $fail) {
                    if (!TaskList::where('id', $value)->where('user_id', auth()->id())->exists()) {
                        $fail('The selected task list does not belong to you.');
                    }
                },
            ],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'frequency' => 'nullable|in:none,daily,weekly,monthly,yearly',
            'status' => 'sometimes|in:open,closed',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $task->update($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('task_attachments', 'public');
                $task->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $image->getClientOriginalName(),
                ]);
            }
        }

        return response()->json($task->load('attachments'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        if ($task->taskList->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->delete();
        return response()->json(null, 204);
    }

    /**
     * Restore the specified archived resource.
     */
    public function restore($id)
    {
        $task = Task::withTrashed()->findOrFail($id);

        if ($task->taskList->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->restore();

        return response()->json($task->load('attachments'));
    }
}
