<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LazadaController;

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

//Lazada
Route::get('/lazada/refresh-token', [LazadaController::class, 'refreshToken'])->name('lazada.refresh_token');
Route::get('/lazada/get-products', [LazadaController::class, 'getProducts'])->name('lazada.get_products');
Route::get('/lazada/get-product-item/{sku}', [LazadaController::class, 'getProductItem'])->name('lazada.get_product_item');
Route::get('/lazada/get-order/{orderId}', [LazadaController::class, 'getOrder'])->name('lazada.get_order');
Route::get('/lazada/get-orders/{status}', [LazadaController::class, 'getOrders'])->name('lazada.get_orders');
Route::get('/lazada/get-order-item/{orderId}', [LazadaController::class, 'getOrderItem'])->name('lazada.get_order_item');