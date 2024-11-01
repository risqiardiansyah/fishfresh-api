<?php

use App\Http\Controllers\Api\AlamatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessagesController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShopsController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'v1'], function () {
  // AUTH
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/register', [AuthController::class, 'register']);

  // Product Pembeli
  Route::get('sell/product', [ProductController::class, 'getSelledProduct']);
  Route::get('slider', [ProductController::class, 'getSlider']);

  Route::get('education', [AlamatController::class, 'getEducation']);

  Route::group(['middleware' => 'auth:sanctum'], function () {
    // Auth
    Route::get('profile', [AuthController::class, 'getProfile']);

    // Shops
    Route::post('shops/request', [ShopsController::class, 'requestShops']);

    Route::get('shops/jenis', [ShopsController::class, 'getJenis']);

    // Product Penjual
    Route::get('product', [ProductController::class, 'getProduct']);
    Route::post('product/add', [ProductController::class, 'addProduct']);
    Route::post('product/delete/{id}', [ProductController::class, 'deleteProduct']);

    // Alamat
    Route::get('alamat', [AlamatController::class, 'getAlamat']);
    Route::post('alamat/add', [AlamatController::class, 'addAlamat']);

    // Alamat
    Route::get('shipping', [ProductController::class, 'getShipping']);
    Route::post('shipping/save', [ProductController::class, 'saveShipping']);

    Route::get('shipping/shops', [ProductController::class, 'getShippingCheckout']);

    Route::get('payment_method', [ProductController::class, 'getPaymentMethod']);

    Route::get('platform_fee', [ProductController::class, 'getPlatformFee']);

    // Order
    Route::post('orders', [ProductController::class, 'createOrder']);

    // Transaksi
    Route::post('user/trans/update', [TransactionController::class, 'updateUserTransaction']);
    Route::get('user/transaction', [TransactionController::class, 'getUserTransaction']);

    // Transaksi
    Route::post('shop/trans/update', [TransactionController::class, 'updateShopTransaction']);
    Route::get('shop/transaction', [TransactionController::class, 'getShopTransaction']);
    Route::get('shop/balance', [TransactionController::class, 'getShopBalance']);

    Route::post('messages/send', [MessagesController::class, 'sendMessages']);
    Route::get('messages', [MessagesController::class, 'getMessages']);

    Route::post('shop/messages/send', [MessagesController::class, 'sendMessagesShop']);
    Route::get('shop/messages/list', [MessagesController::class, 'getMessagesShopList']);
    Route::get('shop/messages', [MessagesController::class, 'getMessagesShop']);

    Route::post('education/add', [AlamatController::class, 'addEducation']);
  });
});
