<?php

namespace SneakyLenny\SourcedAttributes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use SneakyLenny\SourcedAttributes\PendingSourcedAttribute;
use SneakyLenny\SourcedAttributes\SourcedAttributes;

trait HasSourcedAttributes
{
    protected ?bool $overridesState = null;

    public function sourcedAttributes(): MorphMany
    {
        return $this->morphMany(app(SourcedAttributes::class)->modelClass(), 'sourceable');
    }

    public function sourceAttribute(string $attribute): PendingSourcedAttribute
    {
        return new PendingSourcedAttribute($this, $attribute);
    }

    public function scopeWithSourcedAttributes(Builder $query, ?array $attributes = null): Builder
    {
        return $query->with([
            'sourcedAttributes' => function ($relation) use ($attributes) {
                if ($attributes !== null && $attributes !== []) {
                    $relation->whereIn('sourceable_attribute', $attributes);
                }

                $relation->orderByDesc('priority')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            },
        ]);
    }

    public function withOverrides(): static
    {
        $this->overridesState = true;

        return $this;
    }

    public function withoutOverrides(): static
    {
        $this->overridesState = false;

        return $this;
    }

    public function setOverrides(bool $enabled): static
    {
        $this->overridesState = $enabled;

        return $this;
    }

    public function usesOverrides(): bool
    {
        if ($this->overridesState !== null) {
            return $this->overridesState;
        }

        if (property_exists($this, 'overridesDefault')) {
            return (bool) $this->overridesDefault;
        }

        return (bool) config('sourced-attributes.overrides.enabled', true);
    }

    public function syncSourcedAttribute(string $attribute): int
    {
        return $this->syncSourcedAttributes($attribute);
    }

    public function syncSourcedAttributes(?string $attribute = null): int
    {
        $query = $this->sourcedAttributes()
            ->whereNotNull('origin_type')
            ->whereNotNull('origin_id');

        if ($attribute !== null) {
            app(SourcedAttributes::class)->ensureAttributeName($attribute);
            $query->where('sourceable_attribute', $attribute);
        }

        $updated = 0;

        foreach ($query->cursor() as $record) {
            if (! $record->origin) {
                continue;
            }

            $freshValue = data_get($record->origin, $record->origin_attribute);

            if ($freshValue !== $record->value) {
                $record->update(['value' => $freshValue]);
                $updated++;
            }
        }

        if ($this->relationLoaded('sourcedAttributes')) {
            $this->unsetRelation('sourcedAttributes');
        }

        return $updated;
    }

    public function getAttributeValue($key): mixed
    {
        $value = parent::getAttributeValue($key);

        if (! $this->usesOverrides()) {
            return $value;
        }

        if (! $this->shouldResolveSourcedAttribute((string) $key)) {
            return $value;
        }

        [$hasOverride, $overrideValue] = $this->resolveSourcedAttribute((string) $key);

        return $hasOverride ? $overrideValue : $value;
    }

    public function getAttribute($key): mixed
    {
        try {
            $value = parent::getAttribute($key);
        } catch (\Illuminate\Database\Eloquent\MissingAttributeException $exception) {
            if (! is_string($key) || $key === '' || ! $this->shouldResolveVirtualSourcedAttribute($key)) {
                throw $exception;
            }

            [$hasOverride, $overrideValue] = $this->resolveSourcedAttribute($key);

            if (! $hasOverride) {
                throw $exception;
            }

            return $overrideValue;
        }

        if (
            $value !== null
            || ! is_string($key)
            || $key === ''
            || ! $this->shouldResolveVirtualSourcedAttribute($key)
        ) {
            return $value;
        }

        [$hasOverride, $overrideValue] = $this->resolveSourcedAttribute($key);

        return $hasOverride ? $overrideValue : $value;
    }

    public function scopeWhereEffective(Builder $query, string $attribute, mixed $operator, mixed $value = null): Builder
    {
        app(SourcedAttributes::class)->ensureAttributeName($attribute);

        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtolower((string) $operator);

        if (! in_array($operator, app(SourcedAttributes::class)->allowedOperators(), true)) {
            throw new InvalidArgumentException("Unsupported operator [{$operator}] for whereEffective.");
        }

        $sourceTable = app(SourcedAttributes::class)->table();
        $qualifiedKey = $this->qualifyColumn($this->getKeyName());
        $qualifiedAttribute = $this->qualifyColumn($attribute);
        $modelClass = $this::class;

        $winnerValueSql = "select sa.value from {$sourceTable} as sa "
            . "where sa.sourceable_type = ? "
            . "and sa.sourceable_id = {$qualifiedKey} "
            . "and sa.sourceable_attribute = ? "
            . "order by sa.priority desc, sa.created_at desc, sa.id desc "
            . "limit 1";

        return $query->whereRaw(
            "coalesce(({$winnerValueSql}), {$qualifiedAttribute}) {$operator} ?",
            [$modelClass, $attribute, $value]
        );
    }

    public function scopeWhereEffectiveWhen(Builder $query, bool $enabled, string $attribute, mixed $operator, mixed $value = null): Builder
    {
        app(SourcedAttributes::class)->ensureAttributeName($attribute);

        if (func_num_args() === 4) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtolower((string) $operator);

        if (! in_array($operator, app(SourcedAttributes::class)->allowedOperators(), true)) {
            throw new InvalidArgumentException("Unsupported operator [{$operator}] for whereEffectiveWhen.");
        }

        if (! $enabled) {
            return $query->where($this->qualifyColumn($attribute), $operator, $value);
        }

        return $this->scopeWhereEffective($query, $attribute, $operator, $value);
    }

    protected function shouldResolveSourcedAttribute(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if (in_array($key, [$this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()], true)) {
            return false;
        }

        if (! array_key_exists($key, $this->getAttributes())) {
            return false;
        }

        return true;
    }

    protected function shouldResolveVirtualSourcedAttribute(string $key): bool
    {
        if (! $this->usesOverrides() || ! $this->exists) {
            return false;
        }

        if (array_key_exists($key, $this->getAttributes())) {
            return false;
        }

        if ($this->isRelation($key) || $this->relationLoaded($key)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    protected function resolveSourcedAttribute(string $attribute): array
    {
        $record = null;

        if ($this->relationLoaded('sourcedAttributes')) {
            $record = $this->getRelation('sourcedAttributes')
                ->where('sourceable_attribute', $attribute)
                ->sortBy([
                    ['priority', 'desc'],
                    ['created_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first();
        } else {
            $record = $this->sourcedAttributes()
                ->where('sourceable_attribute', $attribute)
                ->orderByDesc('priority')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
        }

        if (! $record) {
            return [false, null];
        }

        return [
            true,
            app(SourcedAttributes::class)->applyCast($this, $attribute, $record->value, $record->cast),
        ];
    }
}
