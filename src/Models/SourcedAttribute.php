<?php

namespace SneakyLenny\SourcedAttributes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SourcedAttribute extends Model
{
    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'cast' => 'string',
        'auto_sync' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable((string) config('sourced-attributes.table', 'sourced_attributes'));
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
