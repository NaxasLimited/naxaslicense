<?php

namespace App\Providers;

use App\Services\Signing\LicenseSignerInterface;
use App\Services\Signing\RsaLicenseSigner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LicenseSignerInterface::class, RsaLicenseSigner::class);
    }

    public function boot(): void
    {
        RateLimiter::for('activation.create', fn (Request $r) => Limit::perHour(config('license.create_limit'))->by(hash('sha256', ($r->input('installation_uuid') ?: 'none').'|'.$r->ip())));
        RateLimiter::for('activation.status', fn (Request $r) => Limit::perHour(config('license.status_limit'))->by(hash('sha256', ($r->route('request_id') ?: 'none').'|'.$r->ip())));
        RateLimiter::for('portal.submit', fn (Request $r) => Limit::perHour(config('license.portal_limit'))->by($r->ip()));
        RateLimiter::for('admin.activation.approve', fn (Request $r) => Limit::perMinute(10)->by((string) $r->user()?->id));
    }
}
