<?php

namespace SneakyLenny\SourcedAttributes\Tests\Support\Models;

class UserOverridesDisabled extends User
{
    protected bool $overridesDefault = false;
}
