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

    $target->sourceAttribute('name')->value('Controller Value', ['priority' => 10]);

    expect($target->fresh()->name)->toBe('Controller Value');
});

it('uses highest priority and oldest created_at as tie breaker', function () {
    $target = TestPerson::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->value('Priority 1', ['priority' => 1]);
    $target->sourceAttribute('name')->value('Priority 5 old', ['priority' => 5]);
    $target->sourceAttribute('name')->value('Priority 5 new', ['priority' => 5]);

    $records = $target->sourcedAttributes()->where('sourceable_attribute', 'name')->orderBy('id')->get();
    $records[1]->update(['created_at' => Carbon::parse('2026-01-01 00:00:00')]);
    $records[2]->update(['created_at' => Carbon::parse('2026-01-01 00:10:00')]);

    expect($target->fresh()->name)->toBe('Priority 5 old');
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
