<?php

namespace Mzm\MyDigitalId;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MyDigitalIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mydigitalid.php', 'mydigitalid');
        $this->registerDefaultLogChannel();
    }

    public function boot(): void
    {
        $this->app['router']->middleware('web')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mydigitalid');

        // Bolehkan <x-mydigitalid::login-button /> digunakan di mana-mana blade
        Blade::anonymousComponentNamespace('mydigitalid::components', 'mydigitalid');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mydigitalid.php' => config_path('mydigitalid.php'),
            ], 'mydigitalid-config');

            // Stub component: publish untuk timpa/ubahsuai reka bentuk butang di aplikasi
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mydigitalid'),
            ], 'mydigitalid-views');
        }
    }

    /**
     * Daftar default log channel untuk audit trail MyDigitalID (storage/logs/mydigitalid-*.log),
     * hanya jika aplikasi belum define channel dengan nama yang sama sendiri dalam config/logging.php.
     */
    private function registerDefaultLogChannel(): void
    {
        $channel = config('mydigitalid.log_channel', 'mydigitalid');

        if (config("logging.channels.{$channel}")) {
            return;
        }

        config([
            "logging.channels.{$channel}" => [
                'driver' => 'daily',
                'path' => storage_path("logs/{$channel}.log"),
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => config('mydigitalid.log_retention_days', 30),
                'permission' => config('mydigitalid.log_file_permission', 0640),
            ],
        ]);
    }
}
