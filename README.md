# Laravel Sourced Attributes

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sneakylenny/laravel-sourced-attributes.svg)](https://packagist.org/packages/sneakylenny/laravel-sourced-attributes)
[![GitHub Tests Action Status](https://github.com/sneakylenny/laravel-sourced-attributes/actions/workflows/run-tests.yml/badge.svg)](https://github.com/sneakylenny/laravel-sourced-attributes/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://github.com/sneakylenny/laravel-sourced-attributes/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/sneakylenny/laravel-sourced-attributes/actions/workflows/fix-php-code-style-issues.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/sneakylenny/laravel-sourced-attributes.svg)](https://packagist.org/packages/sneakylenny/laravel-sourced-attributes)

Laravel Sourced Attributes lets you override Eloquent model attributes with values from either literal input or other model records.

Overrides are persisted in a dedicated table, resolved deterministically (by priority and age), can be cast with Laravel casts, and can be synced from origin records when source data changes.

## Features

- Source an attribute from another model.
- Source an attribute from a direct literal value.
- Deterministic winner selection with highest priority first, then newest `created_at`.
- Re-calling the same source updates the existing source record instead of duplicating it.
- Per-source cast support using built-in Laravel casts and custom cast classes.
- Per-source metadata support via `meta` for origin/audit context.
- Runtime override toggling with `withOverrides()` and `withoutOverrides()`.
- Global and model-level defaults for whether overrides are active.
- Virtual sourced attributes (attributes not present on the model table).
- Manual sync APIs: `syncSourcedAttribute()` and `syncSourcedAttributes()`.
- Optional auto-sync from origin updates, with optional queued execution.
- Query helpers for effective values: `whereEffective()` and `whereEffectiveWhen()`.
- Eager-loading helper for sourced records: `withSourcedAttributes()`.

## Support us

[![Laravel Sourced Attributes banner](.github/assets/readme-banner.svg)](https://packagist.org/packages/sneakylenny/laravel-sourced-attributes)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require sneakylenny/laravel-sourced-attributes
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-sourced-attributes-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-sourced-attributes-config"
```

This is the contents of the published config file:

```php
return [
    // Database table used to store sourced attribute records.
    'table' => 'sourced_attributes',

    // Eloquent model class used for sourced attribute records.
    'model' => SneakyLenny\SourcedAttributes\Models\SourcedAttribute::class,

    // Override resolution behavior.
    'overrides' => [
        // Whether sourced values are applied by default on model reads.
        // Model-level `overridesDefault` and runtime toggles still take precedence.
        'enabled' => true,
    ],

    // Auto-sync behavior for origin-backed sourced records.
    'auto_sync' => [
        // Global toggle for auto-sync listeners.
        'enabled' => true,

        // Default per-record auto_sync value when calling from(...) without explicit option.
        'default' => false,

        // When true, auto-sync runs through the queue; when false, it runs inline.
        'queued' => false,
    ],

    // Default priority for newly stored sourced records.
    'default_priority' => 0,
];
```

## Usage

```php
use App\Models\EntraUser;
use App\Models\User;
use SneakyLenny\SourcedAttributes\Traits\HasSourcedAttributes;

// In your model:
// class User extends Model {
//     use HasSourcedAttributes;
// }

$entraUser = EntraUser::create([
	'external_id' => 'entra-123',
	'profile' => ['displayName' => 'Johnny Doe'],
]);

$user = User::create([
	'name' => 'jhon doe',
]);

// Source the application user's name from the 3rd-party Entra record.
$user->sourceAttribute('name')->from($entraUser, 'profile.displayName');

// Optionally apply a manual override with higher priority.
$user->sourceAttribute('name')->as('Preferred Support Name', ['priority' => 10]);

// Read effective value (resolved override winner).
$resolved = $user->fresh()->name; // "Preferred Support Name"
```

### Attach source metadata

```php
$user->sourceAttribute('name')
    ->from($entraUser, 'profile.displayName')
    ->meta([
        'provider' => 'entra',
        'source_field' => 'displayName',
    ]);

// Also supported via options:
$user->sourceAttribute('title')->as('VIP', [
    'meta' => ['provider' => 'manual'],
]);
```

### Cast sourced values

```php
// Built-in cast:
$user->sourceAttribute('age')->as('42', ['cast' => 'integer']);

// Custom cast class:
$user->sourceAttribute('name')->as('mixed Case', [
	'cast' => App\Casts\UppercaseCast::class,
]);
```

### Override toggles

```php
$model = $user->fresh();

$model->withoutOverrides();
$original = $model->name;

$model->withOverrides();
$effective = $model->name;
```

You can control defaults via:

- Global config: `sourced-attributes.overrides.enabled`
- Model property: `protected bool $overridesDefault = false;`

### Virtual sourced attributes

You can source attributes that are not persisted as model columns.

```php
$user->sourceAttribute('label')->as('VIP');

$label = $user->fresh()->label; // "VIP" when overrides are enabled
```

### Sync from origin changes

```php
$user->sourceAttribute('name')->from($entraUser, 'profile.displayName');

$entraUser->update(['profile' => ['displayName' => 'Ada Byron']]);

// Pull latest value from origin into sourced record(s).
$updatedCount = $user->syncSourcedAttribute('name');
```

Enable auto sync per record:

```php
$user->sourceAttribute('name')->from($entraUser, 'profile.displayName', [
	'auto_sync' => true,
]);
```

When `sourced-attributes.auto_sync.queued` is `true`, auto-sync dispatches a queued job.

### Query by effective value

```php
$ids = Person::query()
	->whereEffective('name', 'Alpha')
	->pluck('id');

// Conditionally skip effective override logic.
$idsWithoutOverrides = Person::query()
	->whereEffectiveWhen(false, 'name', 'Alpha')
	->pluck('id');
```

### Eager load sourced attributes

```php
$people = Person::query()
	->withSourcedAttributes(['name', 'label'])
	->get();
```

### Keep original values and load sourced relation

```php
$person = Person::query()
    ->whereKey($id)
    ->withSourcedAttributes(['name'])
    ->first()
    ->withoutOverrides();

$person->name; // original value from people table
$person->sourcedAttributes; // sourced rows with value/cast/meta/origin
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Tim van Herwijnen](https://github.com/SneakyLenny)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
