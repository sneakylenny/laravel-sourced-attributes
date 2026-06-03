<?php

namespace SneakyLenny\SourcedAttributes;

use Illuminate\Database\Eloquent\Model;
use SneakyLenny\SourcedAttributes\Models\SourcedAttribute;

class PendingSourcedAttribute
{
    public function __construct(
        protected Model $target,
        protected string $attribute,
    ) {
        app(SourcedAttributes::class)->ensurePersisted($this->target);
        app(SourcedAttributes::class)->ensureAttributeName($this->attribute);
    }

    public function from(Model $origin, string $originAttribute, array $options = []): SourcedAttribute
    {
        app(SourcedAttributes::class)->ensurePersisted($origin);
        $cast = app(SourcedAttributes::class)->normalizeCast($options['cast'] ?? null);
        $autoSync = (bool) ($options['auto_sync'] ?? app(SourcedAttributes::class)->defaultAutoSync());

        return $this->target->sourcedAttributes()->updateOrCreate(
            [
                'sourceable_attribute' => $this->attribute,
                'origin_type' => $origin::class,
                'origin_id' => $origin->getKey(),
                'origin_attribute' => $originAttribute,
            ],
            [
                'value' => data_get($origin, $originAttribute),
                'cast' => $cast,
                'auto_sync' => $autoSync,
                'priority' => (int) ($options['priority'] ?? app(SourcedAttributes::class)->defaultPriority()),
            ]
        );
    }

    public function value(mixed $value, array $options = []): SourcedAttribute
    {
        $cast = app(SourcedAttributes::class)->normalizeCast($options['cast'] ?? null);

        return $this->target->sourcedAttributes()->updateOrCreate(
            [
                'sourceable_attribute' => $this->attribute,
                'origin_type' => null,
                'origin_id' => null,
                'origin_attribute' => null,
            ],
            [
                'value' => $value,
                'cast' => $cast,
                'priority' => (int) ($options['priority'] ?? app(SourcedAttributes::class)->defaultPriority()),
            ]
        );
    }
}
