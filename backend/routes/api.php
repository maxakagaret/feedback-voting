<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IdeaController;
use App\Http\Controllers\Api\RoadmapController;
use App\Http\Controllers\Api\VotingController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('demo-accounts', [AuthController::class, 'demoAccounts']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Ideas board — every signed-in user.
    Route::get('ideas', [IdeaController::class, 'index']);
    Route::get('ideas/similar', [IdeaController::class, 'similar']);
    Route::post('ideas', [IdeaController::class, 'store'])->middleware('throttle:ideas');
    Route::get('ideas/{idea}', [IdeaController::class, 'show']);
    Route::post('ideas/{idea}/vote', [IdeaController::class, 'vote']);
    Route::get('roadmap', [RoadmapController::class, 'index']);

    // Votings — every signed-in user votes.
    Route::get('votings', [VotingController::class, 'index']);
    Route::get('votings/{voting}', [VotingController::class, 'show']);
    Route::put('votings/{voting}/ballot', [VotingController::class, 'cast']);
    Route::delete('votings/{voting}/ballot', [VotingController::class, 'withdraw']);

    // Administration.
    Route::middleware('can:admin')->group(function () {
        Route::patch('ideas/{idea}/status', [IdeaController::class, 'changeStatus']);
        Route::put('roadmap/order', [RoadmapController::class, 'reorder']);
        Route::post('votings', [VotingController::class, 'store']);
        Route::post('votings/{voting}/close', [VotingController::class, 'close']);
    });
});
