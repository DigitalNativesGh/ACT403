<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\MonitorController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\ProctorController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

// Student flow
Route::get('/', [StudentController::class, 'create'])->name('student.create');
Route::post('/start', [StudentController::class, 'store'])->middleware('throttle:30,1')->name('student.store');
Route::get('/quiz', [QuizController::class, 'show'])->name('quiz.show');
Route::post('/quiz/save', [QuizController::class, 'save'])->name('quiz.save');
Route::post('/quiz/submit', [QuizController::class, 'submit'])->name('quiz.submit');
Route::post('/quiz/proctor', [ProctorController::class, 'report'])->name('quiz.proctor');
Route::post('/quiz/stream/poll', [StreamController::class, 'poll'])->name('stream.poll');
Route::post('/quiz/stream/answer', [StreamController::class, 'answer'])->name('stream.answer');
Route::get('/result', [QuizController::class, 'result'])->name('quiz.result');
Route::post('/finish', [QuizController::class, 'finish'])->name('quiz.finish');

// Lecturer area
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');

    Route::middleware(AdminAuth::class)->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings');

        Route::get('/questions/template', [QuestionController::class, 'template'])->name('questions.template');
        Route::post('/questions/upload', [QuestionController::class, 'upload'])->name('questions.upload');
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
        Route::delete('/questions', [QuestionController::class, 'destroyAll'])->name('questions.destroyAll');

        Route::get('/monitor', [MonitorController::class, 'show'])->name('monitor');
        Route::get('/monitor/roster', [MonitorController::class, 'roster'])->name('monitor.roster');
        Route::post('/monitor/offers', [MonitorController::class, 'offers'])->name('monitor.offers');
        Route::post('/monitor/answers', [MonitorController::class, 'answers'])->name('monitor.answers');
        Route::post('/monitor/video', [MonitorController::class, 'video'])->name('monitor.video');

        Route::get('/results', [AdminController::class, 'results'])->name('results');
        Route::get('/results/export', [AdminController::class, 'export'])->name('results.export');
        Route::delete('/attempts/{attempt}', [AdminController::class, 'destroyAttempt'])->name('attempts.destroy');
        Route::delete('/attempts', [AdminController::class, 'destroyAllAttempts'])->name('attempts.destroyAll');
    });
});
