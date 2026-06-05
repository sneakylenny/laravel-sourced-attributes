<?php

use SneakyLenny\SourcedAttributes\Models\SourcedAttribute;

return [
    // Database table used to store sourced attribute records.
    'table' => 'sourced_attributes',

    // Eloquent model class used for sourced attribute records.
    'model' => SourcedAttribute::class,

    // Override resolution behavior.
    'overrides' => [
        // Whether sourced values are applied by default on model reads.
        // Model-level `overridesDefault` and runtime toggles still take precedence.
        'enabled' => true,
    ],

    // Auto-sync behavior for origin-backed sourced records.
    'auto_sync' => [
        // Global toggle for auto-sync listeners.
        'enabled' => true,

        // Default per-record auto_sync value when calling from(...) without explicit option.
        'default' => false,

        // When true, auto-sync runs through the queue; when false, it runs inline.
        'queued' => false,
    ],

    // Default priority for newly stored sourced records.
    'default_priority' => 0,
];
