You are a senior Laravel package architect and PHP 8.3+ developer.

I want you to build a Composer-installable Laravel 13 package called:

GOAT

GOAT = a terminal-first Laravel code generator that dramatically reduces repetitive CRUD/feature development.

IMPORTANT:
- This must be a Laravel package, NOT a separate application.
- It must run entirely from the terminal through Artisan commands.
- Do NOT use a web UI.
- Do NOT use a desktop application.
- Do NOT require Ollama, OpenAI, external AI APIs, or any AI service.
- Everything must work locally using PHP/Laravel.
- The package must be Composer-installable.
- Follow Laravel 13 conventions and PHP 8.3+.
- Write clean, maintainable, extensible package architecture.
- Use PSR-4 autoloading.
- Include automated tests.

==================================================
CORE COMMAND
==================================================

The main command must be:

php artisan goat:make Product

When executed, GOAT should provide an interactive terminal experience.

Example:

🐐 GOAT

How do you want to define Product?

❯ Paste Migration
  Paste ERD
  Use Existing Migration

If the user chooses "Paste Migration", allow multiline terminal input.

Example:

Paste your migration.
Press Ctrl+D when finished:

> Schema::create('products', function (Blueprint $table) {
>     $table->id();
>     $table->foreignId('category_id')->constrained();
>     $table->string('name');
>     $table->decimal('price', 10, 2);
>     $table->integer('stock')->default(0);
>     $table->timestamps();
> });

The command must read the complete multiline input from STDIN until EOF/Ctrl+D.

Then GOAT must parse the migration and create an internal schema representation.

==================================================
ERD INPUT
==================================================

GOAT must also support:

php artisan goat:make Product

Then:

How do you want to define Product?

❯ Paste Migration
  Paste ERD
  Use Existing Migration

For ERD input, allow the developer to paste a textual/structured ERD.

Example:

products
---------
id bigint PK
category_id bigint FK -> categories.id
name varchar
price decimal(10,2)
stock integer default 0
created_at timestamp
updated_at timestamp

categories
----------
id bigint PK
name varchar
created_at timestamp
updated_at timestamp

GOAT should parse this into the same internal schema representation used by migration parsing.

Do NOT make each generator parse the migration/ERD independently.

There must be ONE source of truth:

GoatSchema

with supporting objects such as:

GoatTable
GoatColumn
GoatRelationship

==================================================
INTERNAL SCHEMA
==================================================

Design classes similar to:

GoatSchema
- tables

GoatTable
- name
- modelName
- columns
- relationships

GoatColumn
- name
- type
- nullable
- default
- primary
- unique
- unsigned
- length
- precision
- scale

GoatRelationship
- type
- relatedTable
- foreignKey
- ownerKey
- relatedModel

The exact architecture is up to you, but it must be clean and extensible.

==================================================
GENERATED FILES
==================================================

GOAT must generate:

1. Migration
2. Model
3. Form Request(s)
4. API Resource
5. Controller
6. Service
7. Repository
8. Policy
9. Tests

Example:

Product
├── migration
├── Product.php
├── StoreProductRequest.php
├── UpdateProductRequest.php
├── ProductResource.php
├── ProductController.php
├── ProductService.php
├── ProductRepository.php
├── ProductPolicy.php
└── ProductTest.php

==================================================
GENERATOR ARCHITECTURE
==================================================

Each artifact must have its own generator.

Example:

Generators/
    ModelGenerator.php
    MigrationGenerator.php
    RequestGenerator.php
    ResourceGenerator.php
    ControllerGenerator.php
    ServiceGenerator.php
    RepositoryGenerator.php
    PolicyGenerator.php
    TestGenerator.php

Each generator receives the same GoatSchema/GoatTable data.

For example:

ModelGenerator:
- understands Eloquent models
- generates fillable fields
- casts where appropriate
- generates relationships

MigrationGenerator:
- understands Laravel migrations
- generates Schema::create()
- generates columns
- generates foreign keys
- generates indexes
- generates timestamps

RequestGenerator:
- generates validation rules based on schema

ResourceGenerator:
- generates JSON resource fields

ControllerGenerator:
- generates CRUD methods

ServiceGenerator:
- generates service methods

RepositoryGenerator:
- generates repository CRUD methods

PolicyGenerator:
- generates policy methods

TestGenerator:
- generates feature tests

Do NOT create one giant generator class.

==================================================
STUB SYSTEM
==================================================

Every generated artifact must use its own stub.

Create:

stubs/
    model.stub
    migration.stub
    request.stub
    resource.stub
    controller.stub
    service.stub
    repository.stub
    policy.stub
    test.stub

Users must be able to publish and customize these stubs.

Example:

php artisan vendor:publish --tag=goat-stubs

The generator must first look for the application's customized GOAT stub and fall back to the package's default stub.

==================================================
CONFIGURATION
==================================================

Create:

config/goat.php

Example configuration:

return [

    'paths' => [
        'model' => app_path('Models'),
        'migration' => database_path('migrations'),
        'request' => app_path('Http/Requests'),
        'resource' => app_path('Http/Resources'),
        'controller' => app_path('Http/Controllers'),
        'service' => app_path('Services'),
        'repository' => app_path('Repositories'),
        'policy' => app_path('Policies'),
        'test' => base_path('tests/Feature'),
    ],

    'generate' => [
        'model' => true,
        'migration' => true,
        'request' => true,
        'resource' => true,
        'controller' => true,
        'service' => true,
        'repository' => true,
        'policy' => true,
        'test' => true,
    ],

];

All output paths must be configurable.

==================================================
CLI OPTIONS
==================================================

Support:

php artisan goat:make Product

php artisan goat:make Product --from=migration

php artisan goat:make Product --from=erd

php artisan goat:make Product --from=database/migrations/xxxx_create_products_table.php

Allow selecting generated components:

php artisan goat:make Product --only=model,resource,request

Allow excluding components:

php artisan goat:make Product --except=policy,test

Support:

--force

to overwrite existing files.

Also provide helpful validation and error messages.

==================================================
TERMINAL UX
==================================================

The terminal experience is very important.

Example:

$ php artisan goat:make Product

🐐 GOAT

How do you want to define Product?

❯ Paste Migration
  Paste ERD
  Use Existing Migration

Paste your schema.
Press Ctrl+D when finished:

...

✓ Schema detected

Product
├── id
├── category_id → categories.id
├── name
├── price
├── stock
├── created_at
└── updated_at

Generate:

✓ Model
✓ Migration
✓ Requests
✓ Resource
✓ Controller
✓ Service
✓ Repository
✓ Policy
✓ Tests

Generating...

✓ Product model
✓ Product migration
✓ Product requests
✓ Product resource
✓ Product controller
✓ Product service
✓ Product repository
✓ Product policy
✓ Product tests

🐐 GOAT generated Product successfully!

Show the exact files created at the end.

Use Laravel's console styling where appropriate.

==================================================
PACKAGE STRUCTURE
==================================================

Create this architecture:

laravel-goat/
├── src/
│   ├── Commands/
│   │   └── MakeCommand.php
│   │
│   ├── Parsers/
│   │   ├── MigrationParser.php
│   │   └── ErdParser.php
│   │
│   ├── Schema/
│   │   ├── GoatSchema.php
│   │   ├── GoatTable.php
│   │   ├── GoatColumn.php
│   │   └── GoatRelationship.php
│   │
│   ├── Generators/
│   │   ├── ModelGenerator.php
│   │   ├── MigrationGenerator.php
│   │   ├── RequestGenerator.php
│   │   ├── ResourceGenerator.php
│   │   ├── ControllerGenerator.php
│   │   ├── ServiceGenerator.php
│   │   ├── RepositoryGenerator.php
│   │   ├── PolicyGenerator.php
│   │   └── TestGenerator.php
│   │
│   ├── Support/
│   │   ├── StubRenderer.php
│   │   ├── NameResolver.php
│   │   └── FileWriter.php
│   │
│   └── GoatServiceProvider.php
│
├── config/
│   └── goat.php
│
├── stubs/
│   ├── model.stub
│   ├── migration.stub
│   ├── request.stub
│   ├── resource.stub
│   ├── controller.stub
│   ├── service.stub
│   ├── repository.stub
│   ├── policy.stub
│   └── test.stub
│
├── tests/
│   ├── Unit/
│   └── Feature/
│
├── composer.json
├── phpunit.xml
└── README.md

==================================================
COMPOSER
==================================================

Use a Composer package configuration similar to:

{
    "name": "yourname/laravel-goat",
    "description": "Terminal-first Laravel code generator from migrations and ERDs",
    "type": "library",
    "license": "MIT",

    "require": {
        "php": "^8.3",
        "illuminate/console": "^13.0",
        "illuminate/filesystem": "^13.0",
        "illuminate/support": "^13.0"
    },

    "autoload": {
        "psr-4": {
            "Goat\\": "src/"
        }
    },

    "extra": {
        "laravel": {
            "providers": [
                "Goat\\GoatServiceProvider"
            ]
        }
    },

    "minimum-stability": "stable",
    "prefer-stable": true
}

==================================================
SERVICE PROVIDER
==================================================

Create GoatServiceProvider.

It should:

- merge config
- register the Artisan command
- publish config
- publish stubs
- follow Laravel package development conventions

==================================================
PARSER REQUIREMENTS
==================================================

MigrationParser must understand common Laravel Blueprint syntax.

At minimum:

$table->id();
$table->string('name');
$table->text('description');
$table->integer('stock');
$table->bigInteger('value');
$table->decimal('price', 10, 2);
$table->boolean('active');
$table->date('published_at');
$table->datetime('expires_at');
$table->timestamp('created_at');
$table->foreignId('category_id')->constrained();
$table->foreignId('user_id')->nullable()->constrained();
$table->uuid('uuid');
$table->json('metadata');
$table->enum('status', ['draft', 'published']);
$table->unique('email');
$table->timestamps();
$table->softDeletes();

It should detect:

- primary keys
- foreign keys
- nullable
- defaults
- unique
- indexes
- relationships
- timestamps
- soft deletes

The parser should be designed so more Blueprint methods can be added later.

==================================================
MODEL GENERATION
==================================================

For:

category_id -> categories.id

generate an appropriate relationship such as:

public function category()
{
    return $this->belongsTo(Category::class);
}

Also infer reverse relationships when possible.

For example:

Category
hasMany(Product::class)

Use appropriate imports.

Generate fillable fields intelligently.

==================================================
REQUEST GENERATION
==================================================

Generate:

StoreProductRequest
UpdateProductRequest

Infer validation rules from the schema.

Examples:

string -> string
integer -> integer
decimal -> numeric
boolean -> boolean
nullable -> nullable
unique -> unique validation
foreign key -> exists validation

Do not blindly generate incorrect rules.

==================================================
RESOURCE GENERATION
==================================================

Generate a Laravel JsonResource with schema fields.

Relationships should be handled appropriately.

==================================================
CONTROLLER GENERATION
==================================================

Generate a conventional Laravel controller:

index()
store()
show()
update()
destroy()

Use Form Requests.

Use Resource where appropriate.

Delegate business logic to ProductService.

==================================================
SERVICE GENERATION
==================================================

Generate ProductService with methods such as:

index()
create()
show()
update()
delete()

Business logic belongs here rather than inside the controller.

==================================================
REPOSITORY GENERATION
==================================================

Generate ProductRepository.

Keep database/query logic here.

The service should depend on the repository.

==================================================
POLICY GENERATION
==================================================

Generate:

viewAny()
view()
create()
update()
delete()
restore()
forceDelete()

Keep the implementation simple and customizable through the stub.

==================================================
TEST GENERATION
==================================================

Generate Laravel feature tests for the resource.

At minimum test:

- index
- create/store
- show
- update
- delete

Tests should be generated from the schema where possible.

==================================================
NAMING
==================================================

GOAT must intelligently convert:

products -> Product
product_items -> ProductItem
category_id -> category
user_id -> user

Support Laravel naming conventions.

Handle singular/plural/table/model names correctly.

==================================================
SAFETY
==================================================

Never overwrite existing files unless:

--force

was provided.

If files already exist, clearly tell the user.

Example:

⚠ Product.php already exists.

Use --force to overwrite.

==================================================
ERROR HANDLING
==================================================

Handle:

- invalid migration
- invalid ERD
- empty input
- unsupported column type
- invalid table name
- missing schema information
- file permission problems

Errors should be readable and actionable.

Do not dump raw stack traces during normal CLI usage.

==================================================
TESTING
==================================================

Write tests for:

1. Migration parsing
2. ERD parsing
3. Column detection
4. Relationship detection
5. Naming conversion
6. Model generation
7. Request generation
8. Resource generation
9. Controller generation
10. Service generation
11. Repository generation
12. Policy generation
13. Test generation
14. CLI command
15. --only
16. --except
17. --force
18. existing-file protection

Use realistic Laravel migration examples.

==================================================
IMPLEMENTATION STRATEGY
==================================================

Build this incrementally.

Phase 1:
- package skeleton
- composer.json
- service provider
- config
- Artisan command
- multiline STDIN input

Phase 2:
- GoatSchema
- GoatTable
- GoatColumn
- GoatRelationship

Phase 3:
- MigrationParser
- ERD parser

Phase 4:
- StubRenderer
- FileWriter
- NameResolver

Phase 5:
- ModelGenerator
- MigrationGenerator

Phase 6:
- RequestGenerator
- ResourceGenerator
- ControllerGenerator

Phase 7:
- ServiceGenerator
- RepositoryGenerator
- PolicyGenerator
- TestGenerator

Phase 8:
- CLI options
- overwrite protection
- polished terminal UX

Phase 9:
- automated tests
- README
- installation instructions
- package usage examples

==================================================
IMPORTANT DEVELOPMENT RULE
==================================================

Do not give me only conceptual explanations.

Actually create the implementation.

For every file you create:
1. Show the file path.
2. Show the complete file contents.
3. Explain briefly what it does.
4. Make sure namespaces/imports are correct.
5. Make sure the code is compatible with Laravel 13 and PHP 8.3+.
6. Keep the implementation production-quality.

Start with Phase 1.

After Phase 1 is complete, stop and wait for me to say:

NEXT

Then continue to Phase 2.

Do not skip architectural foundations.