<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TradeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'products'])->name('products');
Route::get('/products/{id}', [StorefrontController::class, 'showProduct'])->whereNumber('id')->name('product');
Route::get('/sellers/{id}', [SellerController::class, 'show'])->whereNumber('id')->name('sellers.show');
Route::get('/trades', [TradeController::class, 'index'])->name('trades.index');
Route::get('/trades/{listing}', [TradeController::class, 'show'])->whereNumber('listing')->name('trades.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart', [CartController::class, 'update'])->name('cart.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::post('/orders/reviews', [ReviewController::class, 'store'])->name('orders.reviews.store');
    Route::get('/orders/delivery-location', [AccountController::class, 'deliveryLocation'])->name('orders.delivery-location');
    Route::get('/checkout', [AccountController::class, 'checkoutForm'])->name('checkout');
    Route::post('/checkout', [AccountController::class, 'placeOrder'])->name('checkout.place');
    Route::post('/checkout/paypal/order', [PayPalController::class, 'createOrder'])->middleware('throttle:10,1')->name('checkout.paypal.order');
    Route::post('/checkout/paypal/capture', [PayPalController::class, 'captureOrder'])->middleware('throttle:10,1')->name('checkout.paypal.capture');
    Route::post('/trades', [TradeController::class, 'store'])->name('trades.store');
    Route::post('/trades/{listing}/offers', [TradeController::class, 'offer'])->whereNumber('listing')->name('trades.offers.store');
    Route::post('/trades/{listing}/cancel', [TradeController::class, 'cancelListing'])->whereNumber('listing')->name('trades.cancel');
    Route::post('/trade-offers/{offer}/accept', [TradeController::class, 'accept'])->whereNumber('offer')->name('trade-offers.accept');
    Route::post('/trade-offers/{offer}/decline', [TradeController::class, 'decline'])->whereNumber('offer')->name('trade-offers.decline');
    Route::post('/trade-offers/{offer}/cancel', [TradeController::class, 'cancelOffer'])->whereNumber('offer')->name('trade-offers.cancel');
    Route::post('/trade-offers/{offer}/confirm', [TradeController::class, 'confirmHandoff'])->whereNumber('offer')->name('trade-offers.confirm');
});

Route::prefix('delivery')
    ->name('delivery.')
    ->middleware(['auth', 'delivery.rider'])
    ->group(function (): void {
        Route::get('/', [DeliveryController::class, 'index'])->name('orders');
        Route::post('/orders', [DeliveryController::class, 'update'])->name('orders.update');
        Route::post('/location', [DeliveryController::class, 'updateLocation'])->middleware('throttle:12,1')->name('location');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/products', [AdminProductController::class, 'index'])->name('products');
        Route::post('/products/save', [AdminProductController::class, 'save'])->name('products.save');
        Route::post('/products/action', [AdminProductController::class, 'action'])->name('products.action');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
        Route::post('/categories/save', [CategoryController::class, 'save'])->name('categories.save');
        Route::post('/categories/delete', [CategoryController::class, 'delete'])->name('categories.delete');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::put('/orders', [OrderController::class, 'update'])->name('orders.update');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::put('/users', [UserController::class, 'update'])->name('users.update');
        Route::get('/reports', ReportController::class)->name('reports');
        Route::get('/content', [ContentController::class, 'index'])->name('content');
        Route::put('/content/settings', [ContentController::class, 'saveSettings'])->name('content.settings');
        Route::post('/content/announcements', [ContentController::class, 'saveAnnouncement'])->name('content.announcements.save');
        Route::post('/content/announcements/action', [ContentController::class, 'announcementAction'])->name('content.announcements.action');
    });
