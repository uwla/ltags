<?php

namespace Uwla\Ltags;

use Illuminate\Support\ServiceProvider;

class TagServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // publishes migrations
        $src = __DIR__ . '/' . '../database/migrations/create_tags_tables.php';
        $dest = $this->app->databasePath(
            'migrations/2023_03_23_000000_create_tags_tables.php'
        );
        $this->publishes([$src => $dest], 'migrations');
    }
}
