<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');

Route::post('/test', [TestController::class, 'index'])->name('test.index')->middleware('auth');


Route::get('/test/login', [TestController::class, 'form'])->name('test.form');
Route::post('/test/login', [TestController::class, 'login'])->name('test.login')->middleware('auth');
Route::post('/test/login2', [TestController::class, 'login2'])->name('test.login2')->middleware('auth');