<?php

// config for Sneakylenny/LaravelAttributeOverrides
return [

    /*
    |--------------------------------------------------------------------------
    | Overrides Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the database table that stores attribute overrides.
    |
    */
    'table' => 'attribute_overrides',

    /*
    |--------------------------------------------------------------------------
    | Apply Overrides Automatically
    |--------------------------------------------------------------------------
    |
    | When enabled, the highest-priority active override for each attribute
    | will be returned transparently when accessing model attributes via
    | the standard Eloquent attribute accessor.
    |
    */
    'apply_automatically' => true,

];
