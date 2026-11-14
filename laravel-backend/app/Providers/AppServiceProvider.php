<?php

namespace App\Providers;

use App\Support\TradeHubContent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $view->with('siteContent', app(TradeHubContent::class));
            $view->with('cartCount', array_sum(session('cart', [])));
        });
    }
}
