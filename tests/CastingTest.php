<?php

use SneakyLenny\SourcedAttributes\Tests\Support\Casts\UppercaseCast;
use SneakyLenny\SourcedAttributes\Tests\Support\Models\User;

it('applies a built in cast to the sourced value', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->as('42', ['cast' => 'integer']);

    expect($target->fresh()->name)->toBeInt()->toBe(42);
});

it('applies a custom cast class to the sourced value', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    $target->sourceAttribute('name')->as('mixed Case', ['cast' => UppercaseCast::class]);

    expect($target->fresh()->name)->toBe('MIXED CASE');
});

it('rejects non cast classes passed as a sourced cast', function () {
    $target = User::create([
        'name' => 'Original Name',
    ]);

    expect(fn () => $target->sourceAttribute('name')->as('X', ['cast' => stdClass::class]))
        ->toThrow(InvalidArgumentException::class, 'is not a valid Laravel cast');
});
