<?php

use App\Http\Controllers\Api\v1\ApiPostController;
use App\Http\Controllers\Api\v1\ApiFooController;
use App\Http\Controllers\Api\v1\ApiPollController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('v1/posts', ApiPostController::class)
    ->middlewareFor(['index', 'show'], ['auth:sanctum', 'abilities:posts:read'])
    ->middlewareFor(['store'], ['auth:sanctum', 'abilities:posts:create'])
    ->middlewareFor(['update'], ['auth:sanctum', 'abilities:posts:update'])
    ->middlewareFor(['destroy'], ['auth:sanctum', 'abilities:posts:delete']);

Route::get('/v1/polls/{token}', [ApiPollController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/foo', [ApiFooController::class, 'show']);
    Route::post('/v1/foo', [ApiFooController::class, 'store']);
    Route::get('/v1/polls', [ApiPollController::class, 'index']);
    Route::delete('/v1/polls/{id}', [ApiPollController::class, 'remove']);
    Route::post('/v1/polls',             [ApiPollController::class, 'store']);
    Route::put('/v1/polls/{id}',         [ApiPollController::class, 'update']);
    Route::post('/v1/polls/{id}/launch', [ApiPollController::class, 'launch']);
});
