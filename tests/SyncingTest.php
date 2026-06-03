<?php

use Illuminate\Support\Facades\Queue;
use SneakyLenny\SourcedAttributes\Jobs\SyncSourcedAttributesFromOrigin;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\TestPerson;

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

it('dispatches queued auto sync job when queue mode is enabled', function () {
    Queue::fake();
    config()->set('sourced-attributes.auto_sync.queued', true);

    $source = TestPerson::create([
        'name' => 'source',
        'data' => ['FirstName' => 'Sourced Name'],
    ]);

    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->from($source, 'data.FirstName', ['auto_sync' => true]);

    $source->update(['data' => ['FirstName' => 'Queued Sync']]);

    Queue::assertPushed(SyncSourcedAttributesFromOrigin::class);

    config()->set('sourced-attributes.auto_sync.queued', false);
});
