<?php

namespace App\Providers;

use App\Jobs\Sms\SendSmsJob;
use App\Jobs\Sms\CheckSmsDeliveryStatusJob;
use App\Jobs\Sms\ProcessDeliveryStatusJob;
use App\Models\SmsProviderConfig;
use App\Services\Sms\SmsCreditService;
use App\Services\Sms\SmsDeliveryTrackingService;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsProviderResolver;
use App\Services\Sms\SmsRecipientService;
use App\Services\Sms\SmsResultsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS provider resolver — singleton for the request lifecycle.
        $this->app->singleton(SmsProviderResolver::class, function ($app) {
            return new SmsProviderResolver();
        });

        // SMS message service — singleton.
        $this->app->singleton(SmsMessageService::class, function ($app) {
            return new SmsMessageService($app->make(SmsProviderResolver::class));
        });

        // SMS template service — singleton.
        $this->app->singleton(SmsTemplateService::class, function ($app) {
            return new SmsTemplateService();
        });

        // SMS recipient service — singleton.
        $this->app->singleton(SmsRecipientService::class, function ($app) {
            return new SmsRecipientService();
        });

        // SMS delivery tracking service — singleton.
        $this->app->singleton(SmsDeliveryTrackingService::class, function ($app) {
            return new SmsDeliveryTrackingService($app->make(SmsProviderResolver::class));
        });

        // SMS credit service — singleton.
        $this->app->singleton(SmsCreditService::class, function ($app) {
            return new SmsCreditService($app->make(SmsProviderResolver::class));
        });

        // SMS results service — singleton.
        $this->app->singleton(SmsResultsService::class, function ($app) {
            return new SmsResultsService(
                $app->make(SmsMessageService::class),
                $app->make(SmsTemplateService::class),
                $app->make(SmsRecipientService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register SMS queue jobs for the scheduler/monitor.
        if ($this->app->runningInConsole()) {
            $this->commands([
                //
            ]);
        }
    }
}
