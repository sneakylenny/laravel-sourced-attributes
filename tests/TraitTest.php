<?php

use Illuminate\Support\Carbon;
use SneakyLenny\SourcedAttributes\Tests\Support\Casts\UppercaseCast;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPerson;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPersonOverridesDisabled;

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

    $target->sourceAttribute('name')->value('Controller Value', ['priority' => 10]);

    expect($target->fresh()->name)->toBe('Controller Value');
});

it('uses highest priority and oldest created_at as tie breaker', function () {
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

    expect($target->fresh()->name)->toBe('Priority 5 old');
});

it('updates existing value source record when recalled', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->value('First Value', ['priority' => 1]);
    $target->sourceAttribute('name')->value('Second Value', ['priority' => 10]);

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

it('can sync sourced attribute snapshots from origin records', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');
    $source->update(['data' => ['FirstName' => 'Renamed']]);

    expect($target->fresh()->name)->toBe('Sourced Name');

    $updated = $target->syncSourcedAttribute('name');

    expect($updated)->toBe(1)
        ->and($target->fresh()->name)->toBe('Renamed');
});

it('updates sourced record and target value after origin changes and sync runs', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Initial'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $record = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->first();

    expect($record->value)->toBe('Initial')
        ->and($target->fresh()->name)->toBe('Initial');

    $source->update(['data' => ['FirstName' => 'Updated by Origin']]);

    $changed = $target->syncSourcedAttribute('name');
    $record->refresh();

    expect($changed)->toBe(1)
        ->and($record->value)->toBe('Updated by Origin')
        ->and($target->fresh()->name)->toBe('Updated by Origin');
});

it('can filter by effective value with whereEffective', function () {
    $baseMatch = TestPerson::create(['name' => 'Alpha']);
    $overrideMatch = TestPerson::create(['name' => 'Beta']);
    $noMatch = TestPerson::create(['name' => 'Gamma']);

    $overrideMatch->sourceAttribute('name')->value('Alpha', ['priority' => 10]);
    $noMatch->sourceAttribute('name')->value('Zeta', ['priority' => 10]);

    $ids = TestPerson::query()
        ->whereEffective('name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id, $overrideMatch->id]);
});

it('applies a built in cast to the sourced value', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->value('42', ['cast' => 'integer']);

    expect($target->fresh()->name)->toBeInt()->toBe(42);
});

it('applies a custom cast class to the sourced value', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->value('mixed Case', ['cast' => UppercaseCast::class]);

    expect($target->fresh()->name)->toBe('MIXED CASE');
});

it('rejects non cast classes passed as a sourced cast', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    expect(fn() => $target->sourceAttribute('name')->value('X', ['cast' => \stdClass::class]))
        ->toThrow(\InvalidArgumentException::class, 'is not a valid Laravel cast');
});

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
    config()->set('sourced-attributes.overrides_default', false);

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

    config()->set('sourced-attributes.overrides_default', true);
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

    $overrideOnly->sourceAttribute('name')->value('Alpha', ['priority' => 10]);

    $ids = TestPerson::query()
        ->whereEffectiveWhen(false, 'name', 'Alpha')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$baseMatch->id]);
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

it('auto syncs sourced snapshots on origin update when auto_sync is enabled', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['auto_sync' => true]);

    $source->update(['data' => ['FirstName' => 'Auto Synced']]);

    expect($target->fresh()->name)->toBe('Auto Synced');
});

it('does not auto sync sourced snapshots by default', function () {
    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName');

    $source->update(['data' => ['FirstName' => 'Not Synced']]);

    expect($target->fresh()->name)->toBe('Sourced Name');
});
