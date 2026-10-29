<?php

namespace App\Providers;

use App\Storage\DatabaseFilesystemAdapter;
use App\Storage\StoredFileRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

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
        Storage::extend('database', function ($app, array $config): FilesystemAdapter {
            $adapter = new DatabaseFilesystemAdapter(
                new StoredFileRepository(DB::connection($config['connection'] ?? null)->getPdo()),
                $config['namespace'],
            );

            return new FilesystemAdapter(new Filesystem($adapter, ['visibility' => 'private']), $adapter, $config);
        });
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
    }
}
