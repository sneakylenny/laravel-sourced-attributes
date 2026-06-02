<?php

namespace Sneakylenny\LaravelAttributeOverrides\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Sneakylenny\LaravelAttributeOverrides\AttributeOverride;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasOverrides
{
    /**
     * Get all override records for this model.
     */
    public function attributeOverrides(): MorphMany
    {
        return $this->morphMany(AttributeOverride::class, 'overridable');
    }

    /**
     * Create or update an attribute override.
     *
     * @param  string  $attribute  The model attribute to override.
     * @param  mixed   $value      The overriding value.
     * @param  array{priority?: int, origin?: string}  $options
     */
    public function override(string $attribute, mixed $value, array $options = []): AttributeOverride
    {
        $priority = $options['priority'] ?? 0;
        $origin = $options['origin'] ?? null;

        $override = $this->attributeOverrides()->updateOrCreate(
            ['attribute' => $attribute, 'origin' => $origin],
            ['value' => $this->serializeOverrideValue($value), 'priority' => $priority],
        );

        $this->forgetCachedOverrides();

        return $override;
    }

    /**
     * Remove overrides for a given attribute, optionally filtered by origin.
     *
     * @param  string|null  $origin  When provided only overrides from this origin are removed.
     */
    public function removeOverride(string $attribute, ?string $origin = null): int
    {
        $query = $this->attributeOverrides()->where('attribute', $attribute);

        if ($origin !== null) {
            $query->where('origin', $origin);
        }

        $deleted = $query->delete();

        $this->forgetCachedOverrides();

        return $deleted;
    }

    /**
     * Remove all overrides, optionally filtered by origin.
     */
    public function removeAllOverrides(?string $origin = null): int
    {
        $query = $this->attributeOverrides();

        if ($origin !== null) {
            $query->where('origin', $origin);
        }

        $deleted = $query->delete();

        $this->forgetCachedOverrides();

        return $deleted;
    }

    /**
     * Get the override records for an attribute (all or highest-priority one).
     *
     * @return Collection<int, AttributeOverride>|AttributeOverride|null
     */
    public function getOverrides(?string $attribute = null): Collection|AttributeOverride|null
    {
        if ($attribute === null) {
            return $this->attributeOverrides()->orderByDesc('priority')->get();
        }

        return $this->attributeOverrides()
            ->where('attribute', $attribute)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * Get the effective (highest-priority) override for a specific attribute.
     */
    public function getActiveOverride(string $attribute): ?AttributeOverride
    {
        return $this->getCachedOverridesForAttribute($attribute)
            ->sortByDesc('priority')
            ->first();
    }

    /**
     * Determine whether any override exists for the given attribute.
     */
    public function hasOverride(string $attribute): bool
    {
        return $this->getActiveOverride($attribute) !== null;
    }

    /**
     * Get the original (non-overridden) value straight from the model attributes array.
     */
    public function originalAttribute(string $attribute): mixed
    {
        return parent::getAttribute($attribute);
    }

    /**
     * Override Eloquent's getAttribute to transparently return the highest-priority
     * override value when `apply_automatically` is enabled in the config.
     */
    public function getAttribute($key): mixed
    {
        if (config('attribute-overrides.apply_automatically', true)) {
            $override = $this->getActiveOverride($key);

            if ($override !== null) {
                return $this->unserializeOverrideValue($override->value);
            }
        }

        return parent::getAttribute($key);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Serialize a value so it can be stored in the `value` (text) column.
     */
    private function serializeOverrideValue(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Unserialize a value retrieved from the `value` column.
     */
    private function unserializeOverrideValue(string $value): mixed
    {
        return unserialize($value);
    }

    /**
     * Return a cached collection of all overrides for this model, keyed by
     * attribute name. Loading once per request avoids N+1 problems when
     * multiple attributes are accessed in a loop.
     *
     * @return Collection<int, AttributeOverride>
     */
    private function getCachedOverrides(): Collection
    {
        if (! isset($this->loadedOverridesCache)) {
            $this->loadedOverridesCache = $this->exists
                ? $this->attributeOverrides()->get()
                : new Collection;
        }

        return $this->loadedOverridesCache;
    }

    /**
     * @return Collection<int, AttributeOverride>
     */
    private function getCachedOverridesForAttribute(string $attribute): Collection
    {
        return $this->getCachedOverrides()->where('attribute', $attribute)->values();
    }

    /**
     * Clear the in-memory cache so the next access re-queries the database.
     */
    private function forgetCachedOverrides(): void
    {
        unset($this->loadedOverridesCache);
    }
}
