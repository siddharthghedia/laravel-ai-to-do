<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TaskListController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public email verification endpoints
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::apiResource('task-lists', TaskListController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{id}/restore', [TaskController::class, 'restore']);
});
