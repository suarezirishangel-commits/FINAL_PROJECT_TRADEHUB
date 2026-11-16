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
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'products'])->name('products');
Route::get('/products/{id}', [StorefrontController::class, 'showProduct'])->whereNumber('id')->name('product');

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
    Route::get('/checkout', [AccountController::class, 'checkoutForm'])->name('checkout');
    Route::post('/checkout', [AccountController::class, 'placeOrder'])->name('checkout.place');
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
