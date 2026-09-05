<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Generators\ControllerGenerator;
use Goat\Generators\MigrationGenerator;
use Goat\Generators\ModelGenerator;
use Goat\Generators\PolicyGenerator;
use Goat\Generators\RepositoryGenerator;
use Goat\Generators\RequestGenerator;
use Goat\Generators\ResourceGenerator;
use Goat\Generators\ServiceGenerator;
use Goat\Generators\TestGenerator;
use Goat\Parsers\MigrationParser;
use Goat\Support\StubRenderer;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    private \Goat\Schema\GoatTable $table;
    private StubRenderer $stubs;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = new MigrationParser();
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
        $schema = $parser->parse($migration, 'Product');
        $this->table = $schema->primaryTableFor('Product');
        $this->stubs = new StubRenderer(new Filesystem());
    }

    public function test_model_generator_produces_fillable_and_relationship(): void
    {
        $gen = new ModelGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class Product extends Model', $out['contents']);
        $this->assertStringContainsString("'name'", $out['contents']);
        $this->assertStringContainsString("'price'", $out['contents']);
        $this->assertStringContainsString("function category()", $out['contents']);
        $this->assertStringContainsString('belongsTo(Category::class', $out['contents']);
        $this->assertStringEndsWith('Product.php', $out['path']);
    }

    public function test_migration_generator_produces_schema(): void
    {
        $gen = new MigrationGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString("Schema::create('products'", $out['contents']);
        $this->assertStringContainsString("\$table->id();", $out['contents']);
        $this->assertStringContainsString("\$table->foreignId('category_id')->constrained();", $out['contents']);
        $this->assertStringContainsString("\$table->string('name');", $out['contents']);
    }

    public function test_request_generator_produces_store_and_update(): void
    {
        $gen = new RequestGenerator($this->stubs);
        $outs = $gen->generate($this->table);
        $this->assertCount(2, $outs);
        $store = $outs[0]['contents'];
        $update = $outs[1]['contents'];
        $this->assertStringContainsString('class StoreProductRequest', $store);
        $this->assertStringContainsString("'name' =>", $store);
        $this->assertStringContainsString("'category_id' =>", $store);
        $this->assertStringContainsString('exists:categories,id', $store);
        $this->assertStringContainsString('class UpdateProductRequest', $update);
        $this->assertStringContainsString('sometimes', $update);
    }

    public function test_resource_generator_includes_fields(): void
    {
        $gen = new ResourceGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductResource', $out['contents']);
        $this->assertStringContainsString("'name' => \$this->name", $out['contents']);
        $this->assertStringContainsString("'price' => \$this->price", $out['contents']);
    }

    public function test_controller_generator_delegates_to_service(): void
    {
        $gen = new ControllerGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductController', $out['contents']);
        $this->assertStringContainsString('ProductService', $out['contents']);
        $this->assertStringContainsString('function index()', $out['contents']);
        $this->assertStringContainsString('function store(', $out['contents']);
        $this->assertStringContainsString('function show(', $out['contents']);
        $this->assertStringContainsString('function update(', $out['contents']);
        $this->assertStringContainsString('function destroy(', $out['contents']);
    }

    public function test_service_generator_has_crud_methods(): void
    {
        $gen = new ServiceGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductService', $out['contents']);
        $this->assertStringContainsString('function paginate', $out['contents']);
        $this->assertStringContainsString('function create', $out['contents']);
        $this->assertStringContainsString('function update', $out['contents']);
        $this->assertStringContainsString('function delete', $out['contents']);
        $this->assertStringContainsString('ProductRepository', $out['contents']);
    }

    public function test_repository_generator_has_query_logic(): void
    {
        $gen = new RepositoryGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductRepository', $out['contents']);
        $this->assertStringContainsString('Product::query()->paginate', $out['contents']);
        $this->assertStringContainsString('function create', $out['contents']);
    }

    public function test_policy_generator_has_methods(): void
    {
        $gen = new PolicyGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductPolicy', $out['contents']);
        $this->assertStringContainsString('function viewAny', $out['contents']);
        $this->assertStringContainsString('function view(', $out['contents']);
        $this->assertStringContainsString('function create(', $out['contents']);
        $this->assertStringContainsString('function update(', $out['contents']);
        $this->assertStringContainsString('function delete(', $out['contents']);
        $this->assertStringContainsString('function restore(', $out['contents']);
        $this->assertStringContainsString('function forceDelete(', $out['contents']);
    }

    public function test_test_generator_has_crud_tests(): void
    {
        $gen = new TestGenerator($this->stubs);
        $out = $gen->generate($this->table);
        $this->assertStringContainsString('class ProductTest', $out['contents']);
        $this->assertStringContainsString('test_can_list_products', $out['contents']);
        $this->assertStringContainsString('test_can_create_product', $out['contents']);
        $this->assertStringContainsString('test_can_show_product', $out['contents']);
        $this->assertStringContainsString('test_can_update_product', $out['contents']);
        $this->assertStringContainsString('test_can_delete_product', $out['contents']);
    }

    public function test_model_soft_deletes_trait(): void
    {
        $parser = new MigrationParser();
        $schema = $parser->parse(<<<'PHP'
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->softDeletes();
            $table->timestamps();
        });
        PHP, 'Post');
        $table = $schema->primaryTable();

        $gen = new ModelGenerator($this->stubs);
        $out = $gen->generate($table);
        $this->assertStringContainsString('SoftDeletes', $out['contents']);
        $this->assertStringContainsString('SoftDeletes', $out['contents']);
    }
}
