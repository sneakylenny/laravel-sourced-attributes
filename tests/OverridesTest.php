<?php

use SneakyLenny\SourcedAttributes\Tests\Support\Models\ThirdPartyUser;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\User;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\UserOverridesDisabled;

it('can toggle sourced attribute overrides on a model instance', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $model = $target->fresh();

    expect($model->name)->toBe('Sourced Name');

    $model->withoutOverrides();
    expect($model->name)->toBe('Original Name');

    $model->withOverrides();
    expect($model->name)->toBe('Sourced Name');
});

it('can configure sourced override usage default per model with a trait property', function () {
    $target = UserOverridesDisabled::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $model = $target->fresh();

    expect($model->usesOverrides())->toBeFalse()
        ->and($model->name)->toBe('Original Name');

    $model->withOverrides();

    expect($model->usesOverrides())->toBeTrue()
        ->and($model->name)->toBe('Sourced Name');
});

it('can configure sourced override usage default globally via config', function () {
    config()->set('sourced-attributes.overrides.enabled', false);

    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $model = $target->fresh();

    expect($model->usesOverrides())->toBeFalse()
        ->and($model->name)->toBe('Original Name');

    $model->withOverrides();

    expect($model->usesOverrides())->toBeTrue()
        ->and($model->name)->toBe('Sourced Name');

    config()->set('sourced-attributes.overrides.enabled', true);
});

it('can resolve sourced attributes that do not exist on the model', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('label')->as('TEST');

    expect($target->fresh()->label)->toBe('TEST');
});

it('does not resolve non-model sourced attributes when overrides are disabled', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('label')->as('TEST');

    $model = $target->fresh()->withoutOverrides();

    expect($model->label)->toBeNull();
});
