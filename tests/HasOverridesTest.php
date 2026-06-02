<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sneakylenny\LaravelAttributeOverrides\Traits\HasOverrides;

// ---------------------------------------------------------------------------
// Inline test model – created in memory, no separate file needed
// ---------------------------------------------------------------------------

class TestProduct extends Model
{
    use HasOverrides;

    protected $table = 'test_products';

    protected $fillable = ['name', 'price', 'description'];
}

// ---------------------------------------------------------------------------
// Helper to create the test products table
// ---------------------------------------------------------------------------

function createTestProductTable(): void
{
    if (! Schema::hasTable('test_products')) {
        Schema::create('test_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

beforeEach(function () {
    createTestProductTable();
    $this->product = TestProduct::create(['name' => 'Original Name', 'price' => '9.99']);
});

it('can store an attribute override', function () {
    $override = $this->product->override('name', 'Overridden Name');

    expect($override->attribute)->toBe('name')
        ->and($override->value)->not->toBeEmpty();
});

it('returns the overridden value when accessing an attribute', function () {
    $this->product->override('name', 'Integration Name', ['origin' => 'salesforce', 'priority' => 1]);

    expect($this->product->name)->toBe('Integration Name');
});

it('returns the original value via originalAttribute()', function () {
    $this->product->override('name', 'Integration Name', ['origin' => 'salesforce', 'priority' => 1]);

    expect($this->product->originalAttribute('name'))->toBe('Original Name');
});

it('returns the highest priority override when multiple exist', function () {
    $this->product->override('name', 'Low Priority Name', ['origin' => 'source_a', 'priority' => 1]);
    $this->product->override('name', 'High Priority Name', ['origin' => 'source_b', 'priority' => 10]);

    expect($this->product->name)->toBe('High Priority Name');
});

it('can check whether an override exists for an attribute', function () {
    expect($this->product->hasOverride('name'))->toBeFalse();

    $this->product->override('name', 'Some Name');

    expect($this->product->hasOverride('name'))->toBeTrue();
});

it('can remove a specific override by attribute', function () {
    $this->product->override('name', 'Override', ['origin' => 'crm']);
    $this->product->removeOverride('name');

    expect($this->product->hasOverride('name'))->toBeFalse();
    expect($this->product->name)->toBe('Original Name');
});

it('can remove an override by attribute and origin', function () {
    $this->product->override('name', 'CRM Name', ['origin' => 'crm', 'priority' => 5]);
    $this->product->override('name', 'ERP Name', ['origin' => 'erp', 'priority' => 3]);

    // Only remove the CRM override
    $this->product->removeOverride('name', 'crm');

    // ERP override should still be active
    expect($this->product->name)->toBe('ERP Name');
});

it('can remove all overrides at once', function () {
    $this->product->override('name', 'Override 1', ['origin' => 'crm']);
    $this->product->override('price', '19.99', ['origin' => 'erp']);

    $this->product->removeAllOverrides();

    expect($this->product->hasOverride('name'))->toBeFalse()
        ->and($this->product->hasOverride('price'))->toBeFalse();
});

it('can remove all overrides from a specific origin', function () {
    $this->product->override('name', 'CRM Name', ['origin' => 'crm']);
    $this->product->override('price', '49.99', ['origin' => 'crm']);
    $this->product->override('name', 'ERP Name', ['origin' => 'erp']);

    $this->product->removeAllOverrides('crm');

    // crm overrides gone
    expect($this->product->hasOverride('price'))->toBeFalse();
    // erp override remains
    expect($this->product->name)->toBe('ERP Name');
});

it('can list all override records for the model', function () {
    $this->product->override('name', 'Override A', ['origin' => 'src_a']);
    $this->product->override('price', '99.99', ['origin' => 'src_b']);

    $overrides = $this->product->getOverrides();

    expect($overrides)->toHaveCount(2);
});

it('can list overrides for a specific attribute', function () {
    $this->product->override('name', 'Override A', ['origin' => 'src_a', 'priority' => 1]);
    $this->product->override('name', 'Override B', ['origin' => 'src_b', 'priority' => 2]);
    $this->product->override('price', '99.99', ['origin' => 'src_c']);

    $nameOverrides = $this->product->getOverrides('name');

    expect($nameOverrides)->toHaveCount(2);
});

it('updates an existing override from the same origin instead of duplicating', function () {
    $this->product->override('name', 'First Value', ['origin' => 'crm', 'priority' => 1]);
    $this->product->override('name', 'Updated Value', ['origin' => 'crm', 'priority' => 2]);

    $overrides = $this->product->getOverrides('name');

    expect($overrides)->toHaveCount(1)
        ->and($this->product->name)->toBe('Updated Value');
});

it('stores and retrieves non-string values transparently', function () {
    $this->product->override('price', 42.5, ['origin' => 'test']);

    expect($this->product->price)->toBe(42.5);
});

it('stores null values as an override', function () {
    $this->product->override('description', null, ['origin' => 'test']);

    expect($this->product->description)->toBeNull()
        ->and($this->product->hasOverride('description'))->toBeTrue();
});

it('can get the active override record', function () {
    $this->product->override('name', 'Active', ['origin' => 'src', 'priority' => 5]);

    $active = $this->product->getActiveOverride('name');

    expect($active)->not->toBeNull()
        ->and($active->priority)->toBe(5)
        ->and($active->origin)->toBe('src');
});
