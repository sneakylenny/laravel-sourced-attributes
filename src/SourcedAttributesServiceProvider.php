<?php

namespace SneakyLenny\SourcedAttributes;

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
            ->hasMigration('create_laravel_sourced_attributes_table')
            ->hasCommand(SourcedAttributesCommand::class);
    }
}
