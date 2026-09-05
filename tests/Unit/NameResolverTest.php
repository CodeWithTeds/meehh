<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Support\NameResolver;
use PHPUnit\Framework\TestCase;

final class NameResolverTest extends TestCase
{
    public function test_model_name_from_table(): void
    {
        $this->assertSame('Product', NameResolver::modelName('products'));
        $this->assertSame('ProductItem', NameResolver::modelName('product_items'));
        $this->assertSame('Category', NameResolver::modelName('categories'));
        $this->assertSame('User', NameResolver::modelName('users'));
    }

    public function test_table_name_from_model(): void
    {
        $this->assertSame('products', NameResolver::tableName('Product'));
        $this->assertSame('product_items', NameResolver::tableName('ProductItem'));
        $this->assertSame('categories', NameResolver::tableName('Category'));
    }

    public function test_relation_method_from_foreign_key(): void
    {
        $this->assertSame('category', NameResolver::relationMethodForForeignKey('category_id'));
        $this->assertSame('user', NameResolver::relationMethodForForeignKey('user_id'));
        $this->assertSame('productItem', NameResolver::relationMethodForForeignKey('product_item_id'));
    }

    public function test_related_model_from_foreign_key(): void
    {
        $this->assertSame('Category', NameResolver::relatedModelFromForeignKey('category_id'));
        $this->assertSame('ProductItem', NameResolver::relatedModelFromForeignKey('product_item_id'));
    }

    public function test_variable_name(): void
    {
        $this->assertSame('product', NameResolver::variableName('Product'));
        $this->assertSame('productItem', NameResolver::variableName('ProductItem'));
    }

    public function test_ensure_valid_name_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NameResolver::ensureValidPhpClassName('123Invalid');
    }
}
