<?php

namespace SneakyLenny\SourcedAttributes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use SneakyLenny\SourcedAttributes\Jobs\SyncSourcedAttributesFromOrigin;
use SneakyLenny\SourcedAttributes\Models\SourcedAttribute;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SourcedAttributesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-sourced-attributes')
            ->hasConfigFile()
            ->hasMigration('create_sourced_attributes_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SourcedAttributes::class, fn (): SourcedAttributes => new SourcedAttributes);
    }

    public function packageBooted(): void
    {
        Event::listen('eloquent.updated: *', function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || $model instanceof SourcedAttribute) {
                return;
            }

            $service = app(SourcedAttributes::class);

            if (! $service->shouldSyncOriginClass($model::class)) {
                return;
            }

            if ($service->autoSyncQueued()) {
                SyncSourcedAttributesFromOrigin::dispatch($model);

                return;
            }

            $service->syncFromOrigin($model);
        });
    }
}
