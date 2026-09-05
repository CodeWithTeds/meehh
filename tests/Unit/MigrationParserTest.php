<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Parsers\MigrationParser;
use PHPUnit\Framework\TestCase;

final class MigrationParserTest extends TestCase
{
    private MigrationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MigrationParser();
    }

    public function test_parses_basic_migration(): void
    {
        $migration = <<<'PHP'
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
        PHP;

        $schema = $this->parser->parse($migration, 'Product');
        $table = $schema->primaryTableFor('Product');

        $this->assertNotNull($table);
        $this->assertSame('products', $table->name);
        $this->assertSame('Product', $table->modelName);
        $this->assertTrue($table->hasTimestamps);
        $this->assertCount(7, $table->columns);

        $id = $table->getColumn('id');
        $this->assertNotNull($id);
        $this->assertTrue($id->primary);

        $categoryId = $table->getColumn('category_id');
        $this->assertNotNull($categoryId);
        $this->assertTrue($categoryId->isForeignKey);
        $this->assertSame('categories', $categoryId->foreignTable);

        $this->assertCount(1, $table->relationships);
        $this->assertSame('belongsTo', $table->relationships[0]->type);
        $this->assertSame('Category', $table->relationships[0]->relatedModel);
    }

    public function test_detects_nullable_unique_and_defaults(): void
    {
        $migration = <<<'PHP'
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->integer('age')->default(18);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        PHP;

        $schema = $this->parser->parse($migration);
        $table = $schema->primaryTable();

        $this->assertNotNull($table);
        $email = $table->getColumn('email');
        $this->assertTrue($email->unique);
        $name = $table->getColumn('name');
        $this->assertTrue($name->nullable);
        $age = $table->getColumn('age');
        $this->assertSame(18, $age->default);
        $this->assertTrue($age->hasDefault);
        $active = $table->getColumn('active');
        $this->assertSame(true, $active->default);
    }

    public function test_detects_soft_deletes_and_enum(): void
    {
        $migration = <<<'PHP'
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('status', ['draft', 'published']);
            $table->softDeletes();
            $table->timestamps();
        });
        PHP;

        $schema = $this->parser->parse($migration);
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $this->assertTrue($table->hasSoftDeletes);
        $this->assertNotNull($table->getColumn('deleted_at'));
        $status = $table->getColumn('status');
        $this->assertNotNull($status);
        $this->assertSame(['draft', 'published'], $status->enumValues);
    }

    public function test_detects_all_supported_types(): void
    {
        $migration = <<<'PHP'
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('stock');
            $table->bigInteger('value');
            $table->decimal('price', 10, 2);
            $table->boolean('active');
            $table->date('published_at');
            $table->datetime('expires_at');
            $table->timestamp('created_custom');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->uuid('uuid');
            $table->json('metadata');
            $table->softDeletes();
            $table->timestamps();
        });
        PHP;

        $schema = $this->parser->parse($migration);
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $this->assertSame('samples', $table->name);
        $this->assertNotNull($table->getColumn('name'));
        $this->assertSame('string', $table->getColumn('name')->type);
        $this->assertSame('text', $table->getColumn('description')->type);
        $this->assertSame('integer', $table->getColumn('stock')->type);
        $this->assertSame('bigInteger', $table->getColumn('value')->type);
        $this->assertSame('decimal', $table->getColumn('price')->type);
        $this->assertSame(10, $table->getColumn('price')->precision);
        $this->assertSame(2, $table->getColumn('price')->scale);
        $this->assertSame('boolean', $table->getColumn('active')->type);
        $this->assertSame('date', $table->getColumn('published_at')->type);
        $this->assertSame('datetime', $table->getColumn('expires_at')->type);
        $this->assertSame('timestamp', $table->getColumn('created_custom')->type);
        $userId = $table->getColumn('user_id');
        $this->assertTrue($userId->isForeignKey);
        $this->assertTrue($userId->nullable);
        $this->assertSame('uuid', $table->getColumn('uuid')->type);
        $this->assertSame('json', $table->getColumn('metadata')->type);
        $this->assertTrue($table->hasSoftDeletes);
    }

    public function test_throws_on_empty_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('');
    }

    public function test_uses_fallback_table_name_when_no_schema_wrapper(): void
    {
        $raw = <<<'PHP'
        $table->string('name');
        $table->integer('stock');
        PHP;
        $schema = $this->parser->parse($raw, 'Product');
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $this->assertSame('products', $table->name);
        $this->assertNotNull($table->getColumn('name'));
    }

    public function test_detects_foreign_key_with_constrained_table(): void
    {
        $migration = <<<'PHP'
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
        });
        PHP;
        $schema = $this->parser->parse($migration);
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $col = $table->getColumn('user_id');
        $this->assertSame('users', $col->foreignTable);
    }
}
