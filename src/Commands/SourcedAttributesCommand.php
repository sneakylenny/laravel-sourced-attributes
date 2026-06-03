<?php

namespace SneakyLenny\SourcedAttributes\Commands;

use Illuminate\Console\Command;

class SourcedAttributesCommand extends Command
{
    public $signature = 'laravel-sourced-attributes';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
