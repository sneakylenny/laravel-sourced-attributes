<?php

use Illuminate\Support\Carbon;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPerson;

it('overrides an attribute from a model source path', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    expect($target->fresh()->name)->toBe('Sourced Name');
});

it('overrides an attribute with a direct literal value', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->as('Controller Value', ['priority' => 10]);

    expect($target->fresh()->name)->toBe('Controller Value');
});

it('uses highest priority and newest created_at as tie breaker', function () {
    $sourceA = TestPerson::create([
        'name' => 'source-a',
        'data' => ['FirstName' => 'Priority 1'],
    ]);
    $sourceB = TestPerson::create([
        'name' => 'source-b',
        'data' => ['FirstName' => 'Priority 5 old'],
    ]);
    $sourceC = TestPerson::create([
        'name' => 'source-c',
        'data' => ['FirstName' => 'Priority 5 new'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
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
    $target = TestPerson::create([
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
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['priority' => 1]);
    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['priority' => 9]);

    $record = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();

    expect($target->sourcedAttributes()->where('sourceable_attribute', 'name')->count())->toBe(1)
        ->and($record->priority)->toBe(9)
        ->and($target->fresh()->name)->toBe('Sourced Name');
});

it('can filter by effective value with whereEffective', function () {
    $baseMatch = TestPerson::create(['name' => 'Alpha']);
    $overrideMatch = TestPerson::create(['name' => 'Beta']);
    $noMatch = TestPerson::create(['name' => 'Gamma']);

    $overrideMatch->sourceAttribute('name')->as('Alpha', ['priority' => 10]);
    $noMatch->sourceAttribute('name')->as('Zeta', ['priority' => 10]);

    $ids = TestPerson::query()
        ->whereEffective('name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id, $overrideMatch->id]);
});

it('can eager load sourced attributes for bulk reads', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $models = TestPerson::query()
        ->whereKey($target->id)
        ->withSourcedAttributes(['name'])
        ->get();

    $model = $models->first();

    expect($model->relationLoaded('sourcedAttributes'))->toBeTrue()
        ->and($model->name)->toBe('Sourced Name');
});

it('can skip effective source subquery when disabled for query filtering', function () {
    $baseMatch = TestPerson::create(['name' => 'Alpha']);
    $overrideOnly = TestPerson::create(['name' => 'Beta']);

    $overrideOnly->sourceAttribute('name')->as('Alpha', ['priority' => 10]);

    $ids = TestPerson::query()
        ->whereEffectiveWhen(false, 'name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id]);
});
