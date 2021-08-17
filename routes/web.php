<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\LazadaUIController;

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

Route::prefix('shopee')->middleware(['auth', 'ec.shopee'])->group(function () {
    // Dashboard
    Route::get('/',[ShopeeController::class, 'index'])->name('shopee.dashboard');
    Route::get('/auth',[ShopeeController::class, 'shopAuth'])->name('shopee.auth');
    Route::get('/init-token',[ShopeeController::class, 'initToken'])->name('shopee.init-token');
    Route::post('/sync-item',[ShopeeController::class, 'syncItem'])->name('shopee.sync-item');
    Route::post('/update-price',[ShopeeController::class, 'updatePrice'])->name('shopee.update-price');
    Route::post('/update-stock',[ShopeeController::class, 'updateStock'])->name('shopee.update-stock');
    Route::post('/salesorder-generate',[ShopeeController::class, 'generateSalesorder'])->name('shopee.salesorder-generate');
    Route::post('/invoice-generate',[ShopeeController::class, 'generateInvoice'])->name('shopee.invoice-generate');
    Route::post('/creditmemo-generate',[ShopeeController::class, 'generateCreditmemo'])->name('shopee.creditmemo-generate');

    // Events
    // Route::get('/fetch-events',[HrCalendarController::class,'fetchEvents'])->name('hr_calendar.fetch_events');
    // Route::post('/store-event',[HrCalendarController::class, 'storeEvent'])->name('hr_calendar.store_event');
});

Route::prefix('lazada')->middleware(['auth', 'ec.lazada'])->group(function () {
    // Test Route for lazada
    Route::get('/',[LazadaUIController::class, 'index'])->name('lazada.dashboard');
});

// Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');

// Route::get('/test', [TestController::class, 'index'])->name('test.index');
// Route::get('/test/login', [TestController::class, 'form'])->name('test.form');
// Route::post('/test/login', [TestController::class, 'login'])->name('test.login')->middleware('auth');
// Route::post('/test/login2', [TestController::class, 'login2'])->name('test.login2')->middleware('auth');