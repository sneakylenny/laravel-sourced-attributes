<?php

use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPerson;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPersonOverridesDisabled;

it('can toggle sourced attribute overrides on a model instance', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
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
    $source = TestPersonOverridesDisabled::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPersonOverridesDisabled::create([
        'name' => 'Original Name',
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

    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
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
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('label')->value('TEST');

    expect($target->fresh()->label)->toBe('TEST');
});

it('does not resolve non-model sourced attributes when overrides are disabled', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('label')->value('TEST');

    $model = $target->fresh()->withoutOverrides();

    expect($model->label)->toBeNull();
});
