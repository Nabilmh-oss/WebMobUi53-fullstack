<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PollDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TokenController;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $posts = Post::orderBy('created_at', 'desc')->with('user')->with('likes')->limit(3)->get();

    return view('home', ['posts' => $posts]);
});

Route::get('/about', function () {
    return view('about');
});

Route::get('/@{username}', [ProfileController::class, 'show'])->where('username', '[A-Za-z0-9-_]+');

Route::resource('posts', PostController::class)->only(['index', 'show']);

Route::controller(AuthController::class)->group(function () {
    Route::get('/auth/register', 'showRegister');
    Route::post('/auth/register', 'register');
    Route::get('/auth/login', 'showLogin')->name('login');
    Route::post('/auth/login', 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/polls/dashboard', PollDashboardController::class)->name('polls.dashboard');
    Route::resource('posts', PostController::class)->except(['index', 'show']);
    Route::singleton('my-profile', MyProfileController::class)->destroyable();
    Route::match(['put', 'patch'], '/likes/{post}', [LikeController::class, 'update']);
    Route::resource('tokens', TokenController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::get('/polls/{token}', function ($token) {
    return view('polls.vote', ['token' => $token]);
})->name('polls.vote');
