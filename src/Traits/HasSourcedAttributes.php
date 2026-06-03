<?php

namespace SneakyLenny\SourcedAttributes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use SneakyLenny\SourcedAttributes\PendingSourcedAttribute;
use SneakyLenny\SourcedAttributes\SourcedAttributes;

trait HasSourcedAttributes
{
    public function sourcedAttributes(): MorphMany
    {
        return $this->morphMany(app(SourcedAttributes::class)->modelClass(), 'sourceable');
    }

    public function sourceAttribute(string $attribute): PendingSourcedAttribute
    {
        return new PendingSourcedAttribute($this, $attribute);
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

        foreach ($query->get() as $record) {
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

        if (! $this->shouldResolveSourcedAttribute((string) $key)) {
            return $value;
        }

        [$hasOverride, $overrideValue] = $this->resolveSourcedAttribute((string) $key);

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
            . "order by sa.priority desc, sa.created_at asc, sa.id asc "
            . "limit 1";

        return $query->whereRaw(
            "coalesce(({$winnerValueSql}), {$qualifiedAttribute}) {$operator} ?",
            [$modelClass, $attribute, $value]
        );
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
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();
        } else {
            $record = $this->sourcedAttributes()
                ->where('sourceable_attribute', $attribute)
                ->orderByDesc('priority')
                ->orderBy('created_at')
                ->orderBy('id')
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
