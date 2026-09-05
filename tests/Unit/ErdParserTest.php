<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Parsers\ErdParser;
use PHPUnit\Framework\TestCase;

final class ErdParserTest extends TestCase
{
    private ErdParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ErdParser();
    }

    public function test_parses_single_table_erd(): void
    {
        $erd = <<<'ERD'
        products
        ---------
        id bigint PK
        category_id bigint FK -> categories.id
        name varchar
        price decimal(10,2)
        stock integer default 0
        created_at timestamp
        updated_at timestamp
        ERD;

        $schema = $this->parser->parse($erd);
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $this->assertSame('products', $table->name);
        $this->assertSame('Product', $table->modelName);
        $this->assertTrue($table->hasTimestamps);

        $categoryId = $table->getColumn('category_id');
        $this->assertNotNull($categoryId);
        $this->assertTrue($categoryId->isForeignKey);
        $this->assertSame('categories', $categoryId->foreignTable);
        $this->assertCount(1, $table->relationships);

        $stock = $table->getColumn('stock');
        $this->assertSame(0, $stock->default);
        $this->assertTrue($stock->hasDefault);
    }

    public function test_parses_multiple_tables(): void
    {
        $erd = <<<'ERD'
        products
        ---------
        id bigint PK
        category_id bigint FK -> categories.id
        name varchar

        categories
        ----------
        id bigint PK
        name varchar
        created_at timestamp
        updated_at timestamp
        ERD;

        $schema = $this->parser->parse($erd);
        $this->assertCount(2, $schema->tables());

        $products = $schema->getTable('products');
        $categories = $schema->getTable('categories');
        $this->assertNotNull($products);
        $this->assertNotNull($categories);
        $this->assertSame('Product', $products->modelName);
        $this->assertSame('Category', $categories->modelName);
    }

    public function test_parses_nullable_and_unique_modifiers(): void
    {
        $erd = <<<'ERD'
        users
        -----
        id bigint PK
        email varchar unique
        name varchar nullable
        age integer default 18
        ERD;

        $schema = $this->parser->parse($erd);
        $table = $schema->getTable('users');
        $this->assertNotNull($table);
        $email = $table->getColumn('email');
        $this->assertTrue($email->unique);
        $name = $table->getColumn('name');
        $this->assertTrue($name->nullable);
        $age = $table->getColumn('age');
        $this->assertSame(18, $age->default);
    }

    public function test_parses_type_with_length_and_precision(): void
    {
        $erd = <<<'ERD'
        products
        --------
        id bigint PK
        name varchar(100)
        price decimal(10,2)
        description text
        ERD;

        $schema = $this->parser->parse($erd);
        $table = $schema->primaryTable();
        $name = $table->getColumn('name');
        $this->assertSame(100, $name->length);
        $price = $table->getColumn('price');
        $this->assertSame(10, $price->precision);
        $this->assertSame(2, $price->scale);
    }

    public function test_throws_on_empty_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('');
    }

    public function test_infers_table_from_fallback(): void
    {
        $erd = <<<'ERD'
        id bigint PK
        name varchar
        ERD;
        $schema = $this->parser->parse($erd, 'Product');
        $table = $schema->primaryTable();
        $this->assertNotNull($table);
        $this->assertSame('products', $table->name);
    }
}
