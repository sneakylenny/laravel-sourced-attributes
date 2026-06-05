<?php

namespace SneakyLenny\SourcedAttributes\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use SneakyLenny\SourcedAttributes\Traits\HasSourcedAttributes;

class User extends Model
{
    use HasSourcedAttributes;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];
}
