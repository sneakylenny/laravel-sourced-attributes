<?php

namespace SneakyLenny\SourcedAttributes;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SourcedAttributes
{
    /**
     * @var array<int, string>
     */
    protected array $builtInCastTypes = [
        'array',
        'bool',
        'boolean',
        'collection',
        'custom_datetime',
        'date',
        'datetime',
        'decimal',
        'double',
        'encrypted',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
        'float',
        'hashed',
        'immutable_date',
        'immutable_datetime',
        'immutable_custom_datetime',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];

    public function table(): string
    {
        return (string) config('sourced-attributes.table', 'sourced_attributes');
    }

    public function modelClass(): string
    {
        return (string) config('sourced-attributes.model', Models\SourcedAttribute::class);
    }

    public function defaultPriority(): int
    {
        return (int) config('sourced-attributes.default_priority', 0);
    }

    public function ensurePersisted(Model $model): void
    {
        if (! $model->exists) {
            throw new InvalidArgumentException('The target model must be persisted before sourcing attributes.');
        }
    }

    public function ensureAttributeName(string $attribute): void
    {
        if (! Str::of($attribute)->match('/^[A-Za-z0-9_]+$/')->isNotEmpty()) {
            throw new InvalidArgumentException("Invalid sourced attribute [{$attribute}].");
        }
    }

    public function normalizeCast(mixed $cast): ?string
    {
        if ($cast === null) {
            return null;
        }

        if (! is_string($cast)) {
            throw new InvalidArgumentException('Sourced attribute cast must be a string or null.');
        }

        $cast = trim($cast);

        if ($cast === '') {
            return null;
        }

        $this->ensureValidCast($cast);

        return $cast;
    }

    public function ensureValidCast(string $cast): void
    {
        $normalized = strtolower($cast);

        if (in_array($normalized, $this->builtInCastTypes, true)) {
            return;
        }

        $baseCast = explode(':', $cast, 2)[0];

        if (! class_exists($baseCast)) {
            throw new InvalidArgumentException("Invalid sourced attribute cast [{$cast}].");
        }

        if (
            is_subclass_of($baseCast, CastsAttributes::class)
            || is_subclass_of($baseCast, CastsInboundAttributes::class)
            || is_subclass_of($baseCast, Castable::class)
        ) {
            return;
        }

        throw new InvalidArgumentException("Sourced attribute cast class [{$baseCast}] is not a valid Laravel cast.");
    }

    public function applyCast(Model $model, string $attribute, mixed $value, ?string $cast): mixed
    {
        if ($cast === null || $cast === '') {
            return $value;
        }

        $probe = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };

        $probe->mergeCasts([$attribute => $cast]);
        $probe->setRawAttributes([$attribute => $value], true);

        return $probe->getAttribute($attribute);
    }

    /**
     * @return array<int, string>
     */
    public function allowedOperators(): array
    {
        return ['=', '!=', '<>', '>', '>=', '<', '<=', 'like', 'not like'];
    }
}
