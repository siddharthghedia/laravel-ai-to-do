<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'file_path', 'file_name'];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return url(Storage::url($this->file_path));
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
