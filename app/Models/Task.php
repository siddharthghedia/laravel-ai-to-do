<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_list_id',
        'title',
        'description',
        'due_date',
        'start_time',
        'end_time',
        'frequency',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function taskList()
    {
        return $this->belongsTo(TaskList::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }
}
