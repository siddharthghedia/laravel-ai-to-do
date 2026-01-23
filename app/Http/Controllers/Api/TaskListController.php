<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskList;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $taskLists = TaskList::where('user_id', auth()->id())->get();
        return response()->json($taskLists);
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
            'name' => 'required|string|max:255',
        ]);

        $taskList = TaskList::create([
            'name' => $validated['name'],
            'user_id' => auth()->id(),
        ]);

        return response()->json($taskList, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TaskList $taskList)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaskList $taskList)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaskList $taskList)
    {
        if ($taskList->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $taskList->update([
            'name' => $validated['name'],
        ]);

        return response()->json($taskList);
    }

    public function destroy(TaskList $taskList)
    {
        if ($taskList->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $taskList->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task list soft deleted successfully',
        ]);
    }
}
