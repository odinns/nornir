<?php

declare(strict_types=1);

namespace App\Providers;

use App\Search\ScoutSearchIndexer;
use App\Search\SearchIndexer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchIndexer::class, ScoutSearchIndexer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
