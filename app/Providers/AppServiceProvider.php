<?php

namespace App\Providers;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));

        // Tangkap N+1 query & atribut yang salah ketik selama development
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // @rupiah($amount) → "Rp 1.000.000"
        Blade::directive('rupiah', fn ($expr) => "<?php echo e(\\".Rupiah::class."::format($expr)); ?>");
    }
}
