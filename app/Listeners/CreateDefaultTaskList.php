<?php

namespace App\Listeners;

use App\Models\TaskList;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateDefaultTaskList
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        TaskList::create([
            'name' => 'My Tasks',
            'user_id' => $event->user->id,
        ]);
    }
}
