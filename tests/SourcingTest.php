<?php

use Illuminate\Support\Carbon;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\ThirdPartyUser;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\User;

it('overrides the default origin attribute with a model source path', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'Sourced Name',
    ]);

    $target->sourceAttribute('name')->from($source);

    expect($target->fresh()->name)->toBe('Sourced Name');
});

it('overrides the default origin attribute with a nested model source path', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    expect($target->fresh()->name)->toBe('Sourced Name');
});

it('overrides an attribute with a direct literal value', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->as('Controller Value', ['priority' => 10]);

    expect($target->fresh()->name)->toBe('Controller Value');
});

it('uses highest priority and newest created_at as tie breaker', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $sourceA = ThirdPartyUser::create([
        'name' => 'source-a',
        'data' => ['FirstName' => 'Priority 1'],
    ]);
    $sourceB = ThirdPartyUser::create([
        'name' => 'source-b',
        'data' => ['FirstName' => 'Priority 5 old'],
    ]);
    $sourceC = ThirdPartyUser::create([
        'name' => 'source-c',
        'data' => ['FirstName' => 'Priority 5 new'],
    ]);

    $target->sourceAttribute('name')->from($sourceA, 'data.FirstName', ['priority' => 1]);
    $target->sourceAttribute('name')->from($sourceB, 'data.FirstName', ['priority' => 5]);
    $target->sourceAttribute('name')->from($sourceC, 'data.FirstName', ['priority' => 5]);

    $records = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->orderBy('id')->get();
    $records[1]->update(['created_at' => Carbon::parse('2026-01-01 00:00:00')]);
    $records[2]->update(['created_at' => Carbon::parse('2026-01-01 00:10:00')]);

    expect($target->fresh()->name)->toBe('Priority 5 new');
});

it('updates existing value source record when recalled', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->as('First Value', ['priority' => 1]);
    $target->sourceAttribute('name')->as('Second Value', ['priority' => 10]);

    $record = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();

    expect($target->sourcedAttributes()->where('sourceable_attribute', 'name')->count())->toBe(1)
        ->and($record->value)->toBe('Second Value')
        ->and($record->priority)->toBe(10)
        ->and($target->fresh()->name)->toBe('Second Value');
});

it('updates existing origin source record when recalled', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['priority' => 1]);
    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['priority' => 9]);

    $record = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();

    expect($target->sourcedAttributes()->where('sourceable_attribute', 'name')->count())->toBe(1)
        ->and($record->priority)->toBe(9)
        ->and($target->fresh()->name)->toBe('Sourced Name');
});

it('can filter by effective value with whereEffective', function () {
    $baseMatch = User::create(['name' => 'Alpha']);
    $overrideMatch = User::create(['name' => 'Beta']);
    $noMatch = User::create(['name' => 'Gamma']);

    $overrideMatch->sourceAttribute('name')->as('Alpha', ['priority' => 10]);
    $noMatch->sourceAttribute('name')->as('Zeta', ['priority' => 10]);

    $ids = User::query()
        ->whereEffective('name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id, $overrideMatch->id]);
});

it('can eager load sourced attributes for bulk reads', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $models = User::query()
        ->whereKey($target->id)
        ->withSourcedAttributes(['name'])
        ->get();

    $model = $models->first();

    expect($model->relationLoaded('sourcedAttributes'))->toBeTrue()
        ->and($model->name)->toBe('Sourced Name');
});

it('stores metadata with from meta chaining', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')
        ->from($source, 'data.FirstName')
        ->meta(['provider' => 'entra', 'field' => 'displayName']);

    $record = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();

    expect($record->meta)->toBe([
        'provider' => 'entra',
        'field' => 'displayName',
    ]);
});

it('stores metadata through options for literal and origin sources', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')->as('Literal', ['meta' => ['type' => 'manual']]);
    $target->sourceAttribute('label')->from($source, 'data.FirstName', ['meta' => ['type' => 'origin']]);

    $nameRecord = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();
    $labelRecord = $target->sourcedAttributes()->where('sourceable_attribute', 'label')->first();

    expect($nameRecord->meta)->toBe(['type' => 'manual'])
        ->and($labelRecord->meta)->toBe(['type' => 'origin']);
});

it('can keep original values while loading sourced attributes for frontend display', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $source = ThirdPartyUser::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target->sourceAttribute('name')
        ->from($source, 'data.FirstName')
        ->meta(['provider' => 'entra']);

    $model = User::query()
        ->whereKey($target->id)
        ->withSourcedAttributes(['name'])
        ->first()
        ->withoutOverrides();

    expect($model->name)->toBe('Original Name')
        ->and($model->relationLoaded('sourcedAttributes'))->toBeTrue()
        ->and($model->sourcedAttributes->first()->value)->toBe('Sourced Name')
        ->and($model->sourcedAttributes->first()->meta)->toBe(['provider' => 'entra']);
});

it('can skip effective source subquery when disabled for query filtering', function () {
    $baseMatch = User::create(['name' => 'Alpha']);
    $overrideOnly = User::create(['name' => 'Beta']);

    $overrideOnly->sourceAttribute('name')->as('Alpha', ['priority' => 10]);

    $ids = User::query()
        ->whereEffectiveWhen(false, 'name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id]);
});
