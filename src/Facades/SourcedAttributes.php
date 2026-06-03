<?php

namespace SneakyLenny\SourcedAttributes\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SneakyLenny\SourcedAttributes\SourcedAttributes
 */
class SourcedAttributes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SneakyLenny\SourcedAttributes\SourcedAttributes::class;
    }
}
