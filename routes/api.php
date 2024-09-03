<?php

use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Route::get('/test', [TestController::class, 'test']);
Route::get('/test2', [TestController::class, 'test2']);

Route::prefix('task')->group(function () {
    Route::get('/dua', [TaskController::class, 'dua'])->name('task.dua');
    Route::get('/tiga', [TaskController::class, 'tiga'])->name('task.tiga');
    Route::get('/empat', [TaskController::class, 'empat'])->name('task.empat');
    Route::get('/empat2', [TaskController::class, 'empat2'])->name('task.empat2');
});
