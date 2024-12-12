<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use App\Models\Configuration;

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
        $settings = Configuration::first();

        if ($settings) {
            // Dynamically set Microsoft configuration
            Config::set('services.microsoft', [
                'client_id' => $settings->ms_client_id,
                'client_secret' => $settings->ms_client_secret,
                'redirect' => env('MICROSOFT_REDIRECT_URI', 'http://example.com/callback'),
                'tenant' => $settings->ms_tenent_id ?? 'common',
                'ms_email_account' => $settings->ms_email_account, 
                'include_tenant_info' => true,
            ]);
        }
    }
}
