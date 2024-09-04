<?php

use App\Http\Controllers\SupportController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Route::get('/test', [TestController::class, 'test']);
Route::post('/register', [RegisteredUserController::class, 'store'])
                ->middleware('guest')
                ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('guest')
                ->name('login');

Route::prefix('task')->group(function () {
    Route::get('/dua', [TaskController::class, 'dua'])->name('task.dua');
    Route::get('/tiga', [TaskController::class, 'tiga'])->name('task.tiga');
    Route::get('/empat', [TaskController::class, 'empat'])->name('task.empat');
    Route::post('/createCustomerlimaEnam', [TaskController::class, 'createCustomerLimaEnam'])->name('task.limaEnam');
    Route::put('/updateCustomerLimaEnam/{customer}', [TaskController::class, 'updateCustomerLimaEnam'])->name('task.updatelimaEnam');
    Route::post('/tujuh', [TaskController::class, 'tujuh'])->name('task.tujuh');
});

Route::prefix('support')->group(function () {
   // users, areas, customers
   Route::get('/users', [SupportController::class, 'users'])->name('support.users');
   Route::get('/customers', [SupportController::class, 'customers'])->name('support.customers');
   Route::get('/areas', [SupportController::class, 'areas'])->name('support.areas');
});
