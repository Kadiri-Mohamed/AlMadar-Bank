<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('signUp', [AuthController::class, 'signUp']);
Route::post('signIn', [AuthController::class, 'signIn']);

// Routes protégées
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('signOut', [AuthController::class, 'signOut']);
    Route::get('me', [ProfileController::class, 'showProfile']);
    Route::put('updateProfile', [ProfileController::class, 'updateProfile']);
    Route::put('updatePassword', [ProfileController::class, 'updatePassword']);
    Route::delete('deleteProfile', [ProfileController::class, 'deleteProfile']);
    
    // Accounts
    Route::get('accounts', [AccountController::class, 'index']);
    Route::post('accounts', [AccountController::class, 'store']);
    Route::get('accounts/{id}', [AccountController::class, 'show']);
    Route::post('accounts/{id}/co-owners', [AccountController::class, 'addCoOwner']);
    Route::delete('accounts/{id}/co-owners/{userId}', [AccountController::class, 'removeCoOwner']);
    Route::post('accounts/{id}/guardian', [AccountController::class, 'assignGuardian']);
    Route::patch('accounts/{id}/convert', [AccountController::class, 'convert']);
    Route::delete('accounts/{id}', [AccountController::class, 'requestClosure']);
    
    // Transfers
    Route::post('transfers', [TransferController::class, 'store']);
    Route::get('transfers/{id}', [TransferController::class, 'show']);
    
    // Transactions
    Route::get('accounts/{accountId}/transactions', [TransactionController::class, 'index']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
});

// Routes admin
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('accounts', [AdminController::class, 'accounts']);
    Route::patch('accounts/{id}/block', [AdminController::class, 'blockAccount']);
    Route::patch('accounts/{id}/unblock', [AdminController::class, 'unblockAccount']);
    Route::patch('accounts/{id}/close', [AdminController::class, 'closeAccount']);
    Route::get('users', [AdminController::class, 'users']);
});