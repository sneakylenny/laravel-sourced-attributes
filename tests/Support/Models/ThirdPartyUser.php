<?php

namespace SneakyLenny\SourcedAttributes\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyUser extends Model
{
    protected $table = 'third_party_users';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];
}
