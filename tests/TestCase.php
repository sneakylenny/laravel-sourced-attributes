<?php

namespace SneakyLenny\SourcedAttributes\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use SneakyLenny\SourcedAttributes\SourcedAttributesServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            SourcedAttributesServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('sourced-attributes.table', 'sourced_attributes');
    }

    protected function setUpDatabase(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('third_party_users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        (include __DIR__ . '/../database/migrations/create_sourced_attributes_table.php.stub')->up();
    }
}
