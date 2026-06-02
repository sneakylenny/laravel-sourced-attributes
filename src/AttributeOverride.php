<?php

namespace Sneakylenny\LaravelAttributeOverrides;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttributeOverride extends Model
{
    protected $fillable = [
        'attribute',
        'value',
        'origin',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    public function getTable(): string
    {
        return config('attribute-overrides.table', 'attribute_overrides');
    }

    public function overridable(): MorphTo
    {
        return $this->morphTo();
    }
}
