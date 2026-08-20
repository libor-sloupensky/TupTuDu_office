<?php

namespace App\Providers;

use App\Mail\Transport\PhpMailTransport;
use Illuminate\Support\Facades\Mail;
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
        // Transport pro hostingy s blokovaným SMTP — viz PhpMailTransport
        Mail::extend('php_mail', fn (array $config) => new PhpMailTransport());
    }
}
