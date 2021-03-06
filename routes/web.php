<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LazadaController;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\LazadaUIController;
use App\Http\Controllers\Lazada2UIController;
use App\Http\Controllers\Tchub\DashboardController;
use App\Http\Controllers\Tchub\ItemMasterController;
use App\Http\Controllers\Tchub\SalesProcessController;

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

Auth::routes(['register'=>false]);

Route::get('/', function () {
    return redirect('login');
});

Route::prefix('shopee')->middleware(['auth', 'ec.shopee'])->group(function () {
    // Dashboard
    Route::get('/',[ShopeeController::class, 'index'])->name('shopee.dashboard');
    Route::get('/auth',[ShopeeController::class, 'shopAuth'])->name('shopee.auth');
    Route::get('/init-token',[ShopeeController::class, 'initToken'])->name('shopee.init-token');
    Route::post('/create-product',[ShopeeController::class, 'createProduct'])->name('shopee.create-product');
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
    // Dashboard for lazada Account 1
    Route::get('/token-status',[LazadaUIController::class, 'displayTokenStatus'])->name('lazada.token-status');
    Route::get('/',[LazadaUIController::class, 'index'])->name('lazada.dashboard');
    Route::post('/refresh-token',[LazadaUIController::class, 'refreshToken'])->name('lazada.refresh-token');
    Route::post('/item-master-integration',[LazadaUIController::class, 'itemMasterIntegration'])->name('lazada.item-master-integration');
    Route::post('/sync-item',[LazadaUIController::class, 'syncItem'])->name('lazada.sync-item');
    Route::post('/update-price',[LazadaUIController::class, 'updatePrice'])->name('lazada.update-price');
    Route::post('/update-stock',[LazadaUIController::class, 'updateStock'])->name('lazada.update-stock');
    Route::post('/sales-order-generate',[LazadaUIController::class, 'generateSalesOrder'])->name('lazada.sales-order-generate');
    Route::post('/invoice-generate',[LazadaUIController::class, 'generateInvoice'])->name('lazada.invoice-generate');
    Route::post('/credit-memo-generate',[LazadaUIController::class, 'generateCreditMemo'])->name('lazada.credit-memo-generate');
});

Route::prefix('lazada2')->middleware(['auth', 'ec.lazada'])->group(function () {
    // Dashboard for lazada Account 2
    Route::get('/token-status',[Lazada2UIController::class, 'displayTokenStatus'])->name('lazada2.token-status');
    Route::get('/',[Lazada2UIController::class, 'index'])->name('lazada2.dashboard');
    Route::post('/refresh-token',[Lazada2UIController::class, 'refreshToken'])->name('lazada2.refresh-token');
    Route::post('/item-master-integration',[Lazada2UIController::class, 'itemMasterIntegration'])->name('lazada2.item-master-integration');
    Route::post('/sync-item',[Lazada2UIController::class, 'syncItem'])->name('lazada2.sync-item');
    Route::post('/update-price',[Lazada2UIController::class, 'updatePrice'])->name('lazada2.update-price');
    Route::post('/update-stock',[Lazada2UIController::class, 'updateStock'])->name('lazada2.update-stock');
    Route::post('/sales-order-generate',[Lazada2UIController::class, 'generateSalesOrder'])->name('lazada2.sales-order-generate');
    Route::post('/invoice-generate',[Lazada2UIController::class, 'generateInvoice'])->name('lazada2.invoice-generate');
    Route::post('/credit-memo-generate',[Lazada2UIController::class, 'generateCreditMemo'])->name('lazada2.credit-memo-generate');

    
});

Route::prefix('tchub')->middleware(['auth', 'ec.tchub'])->group(function () {
    Route::get('/', DashboardController::class)->name('tchub.dashboard');
    Route::post('/create-product', [ItemMasterController::class, 'createProduct'])->name('tchub.create.product');
    Route::post('/update-item-status', [ItemMasterController::class, 'updateItemStatus'])->name('tchub.update.item.status');
    Route::post('/update-prices', [ItemMasterController::class, 'updatePrices'])->name('tchub.update.prices');
    Route::post('/update-stocks', [ItemMasterController::class, 'updateStocks'])->name('tchub.update.stocks');

    Route::post('/generate-sales-order', [SalesProcessController::class, 'generateSalesOrder'])->name('tchub.generate.sales.order');
    Route::get('/pending-orders', [SalesProcessController::class, 'view'])->name('tchub.pending.orders.index');
    Route::post('/pending-orders/{entity_id}', [SalesProcessController::class, 'store'])->name('tchub.pending.order.store');
    Route::post('/delivery-order', [SalesProcessController::class, 'deliveryOrder'])->name('tchub.delivery.order');
    Route::post('/ar-invoice', [SalesProcessController::class, 'arInvoice'])->name('tchub.ar.invoice');
    Route::post('/canceled-order', [SalesProcessController::class, 'canceledOrder'])->name('tchub.canceled.order');

});

// Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');

// Route::get('/test', [TestController::class, 'index'])->name('test.index');
// Route::get('/test/login', [TestController::class, 'form'])->name('test.form');
// Route::post('/test/login', [TestController::class, 'login'])->name('test.login')->middleware('auth');
// Route::post('/test/login2', [TestController::class, 'login2'])->name('test.login2')->middleware('auth');