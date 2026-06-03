<?php

namespace SneakyLenny\SourcedAttributes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use SneakyLenny\SourcedAttributes\Commands\SourcedAttributesCommand;

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
            ->hasViews()
            ->hasMigration('create_sourced_attributes_table')
            ->hasCommand(SourcedAttributesCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SourcedAttributes::class, fn(): SourcedAttributes => new SourcedAttributes);
    }

    public function packageBooted(): void
    {
        Event::listen('eloquent.updated: *', function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            app(SourcedAttributes::class)->syncFromOrigin($model);
        });
    }
}
