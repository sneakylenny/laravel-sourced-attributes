<?php

namespace Sneakylenny\LaravelAttributeOverrides;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AttributeOverridesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('attribute-overrides')
            ->hasConfigFile()
            ->hasMigration('create_attribute_overrides_table');
    }
}
