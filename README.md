<div align="center">

# 🐐 GOAT

### Terminal-first Laravel *Feature* Generator

**Template · Boilerplate · Feature Slice — from a single migration or ERD**

[![PHP ^8.3](https://img.shields.io/badge/PHP-^8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel 11|12|13](https://img.shields.io/badge/Laravel-11%20|%2012%20|%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Tests 40/40](https://img.shields.io/badge/tests-40%2F40-brightgreen?style=flat-square)](tests)
[![License MIT](https://img.shields.io/badge/license-MIT-black?style=flat-square)](LICENSE)
[![Local First](https://img.shields.io/badge/AI-free%20%E2%80%A2%20local--first-0ea5e9?style=flat-square)](#)
[![Version v1.0.0](https://img.shields.io/badge/version-v1.0.0-111827?style=flat-square)](https://github.com/CodeWithTeds/laravel-goat/releases)

*Not just CRUD. Generate a complete, production-ready feature in 2 seconds.*

```bash
php artisan goat:make Product
```

</div>

---

### Why GOAT?

> You paste a schema. GOAT ships the whole slice — not just a controller.

<table>
<tr>
<td>

**You give**
```php
Schema::create('products', function($t){
  $t->id();
  $t->foreignId('category_id')->constrained();
  $t->string('name');
  $t->decimal('price',10,2);
  $t->timestamps();
});
```
*or* a plain ERD:
```
products
---------
id bigint PK
name varchar
price decimal(10,2)
```

</td>
<td>

**You get — template-ready**
```
Product
├── 📄 2024_*_create_products_table.php
├── 🧩 Product.php (fillable, casts, belongsTo)
├── ✅ StoreProductRequest.php
├── ✅ UpdateProductRequest.php
├── 🔌 ProductResource.php
├── 🎮 ProductController.php (thin)
├── 🧠 ProductService.php (business)
├── 🗄️ ProductRepository.php (query)
├── 🔒 ProductPolicy.php
└── 🧪 ProductTest.php
```
Service → Repository, Resource, Policy, Tests included.

</td>
</tr>
</table>

**No AI. No SaaS. No web UI.** Pure PHP — runs 100% locally.

---

### ✨ Feature Generator, not just CRUD

| CRUD generator | **GOAT — Feature / Template / Boilerplate** |
|---|---|
| Model + Controller | **+ Service + Repository (separation)** |
| No validation | **+ Store/Update Requests with inferred rules** |
| No API layer | **+ JsonResource** |
| No auth | **+ Policy (7 methods)** |
| No tests | **+ Feature tests (5 scenarios)** |
| One table = one file | **ERD with N tables → N slices** |
| Hardcoded paths | **All paths & namespaces configurable** |
| Fixed stubs | **Publish & customize — `vendor:publish --tag=goat-stubs`** |

Use it as:
- **CRUD scaffold** for admin panels
- **Feature slice** for clean architecture (Service/Repo)
- **Boilerplate / template** for new domains (`Inventory`, `Order`, `Booking`)
- **Rapid prototyping** from an ERD whiteboard

---

### ⚡ 10 seconds to first feature

```bash
composer require goat/laravel-goat --dev
php artisan goat:make Product
```

```
🐐 GOAT — Laravel Feature Generator

How do you want to define Product?
❯ Paste Migration
  Paste ERD
  Use Existing Migration

Paste your migration. Press Ctrl+D when finished:
> Schema::create('products', ...);
> ^D

✓ Schema detected
 Product (products)
 ├── id bigInteger (PK)
 ├── category_id foreignId → categories.id
 ├── name string
 ├── price decimal
 └── created_at timestamp

Generate:
 ✓ Model  ✓ Migration  ✓ Requests  ✓ Resource  ✓ Controller  ✓ Service  ✓ Repository  ✓ Policy  ✓ Tests

✓ app/Models/Product.php
✓ app/Services/ProductService.php
...
🐐 GOAT generated Product successfully!
```

<details>
<summary>CI / non-interactive</summary>

```bash
cat migration.php | php artisan goat:make Product --from=migration --force
cat erd.txt | php artisan goat:make Product --from=erd --force
php artisan goat:make Product --from=database/migrations/2024_01_01_create_products_table.php
php artisan goat:make Product --only=model,resource,request
php artisan goat:make Product --except=policy,test
```
</details>

---

### 🧬 Inputs — Migration *or* ERD → one schema

**Migration** — understands Blueprint:
`id`, `string`, `text`, `integer`, `bigInteger`, `decimal`, `float`, `boolean`, `date`, `datetime`, `timestamp`, `foreignId`, `uuid`, `json`, `enum`, `softDeletes`, `timestamps`, `unique`, `nullable`, `default`, `constrained`, `index`…

**ERD** — plain text, forgiving:
```
inventories
------------
id bigint PK
product_name varchar
sku varchar UNIQUE
quantity integer
price decimal(10,2)
created_at timestamp
```
*No type?* Inferred (`quantity`→`integer`, `price`→`decimal`, `description`→`text`). Handles `PK`, `FK -> categories.id`, `UNIQUE`, `DEFAULT 0`, `varchar(100)`.

<details>
<summary>ERD full example</summary>

```
products
---------
id bigint PK
category_id bigint FK -> categories.id
name varchar
price decimal(10,2)

categories
----------
id bigint PK
name varchar
```
`GoatSchema` is the single source of truth — parsers feed it, 9 generators consume it.

</details>

---

### 🧩 What gets generated

**`Product.php`** — `fillable`, `casts`, `belongsTo(Category::class)`
```php
protected $fillable = ['category_id','name','price'];
protected function casts(): array { return ['price' => 'decimal:2']; }
public function category(){ return $this->belongsTo(Category::class,'category_id'); }
```

**Requests** — `required`/`sometimes` + `string`/`integer`/`numeric`/`exists:categories,id`/`unique` + `max:255`
**Controller** — thin `index/store/show/update/destroy`, delegates to `ProductService`, uses `ProductResource`
**Service ↔ Repository** — business vs query split, `paginate/all/find/create/update/delete`
**Policy** — `viewAny/view/create/update/delete/restore/forceDelete` (edit stub to fit)
**Test** — `test_can_list_products` … `test_can_delete_product` with factory payloads

---

### 🎨 Customize Everything

```php
// config/goat.php
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
  'namespaces' => ['model' => 'App\\Models', /* ... */],
  'generate' => ['model'=>true, /* ... */],
];
```

Stubs — one per artifact:
```
stubs/model.stub, migration.stub, request.stub, resource.stub,
      controller.stub, service.stub, repository.stub, policy.stub, test.stub
```
```bash
php artisan vendor:publish --tag=goat-stubs
# → resources/stubs/vendor/goat/*.stub — edit once, generate forever
```
`StubRenderer` prefers your published stub, falls back to package default.

---

### 🛡️ Safety & DX

- **Never overwrites** without `--force` → `⚠ Product.php already exists. Use --force`
- Validates `--only`/`--except`, empty input, unsupported types, bad table names — no stack trace dumps
- Naming via `NameResolver` (`products`→`Product`, `product_items`→`ProductItem`, `category_id`→`category`)
- `--only=model,resource --except=policy` composable

---

### 🏗️ Architecture — 2026

```
src/
├── Commands/MakeCommand.php          # UX, STDIN, --from/--only/--except/--force
├── Parsers/MigrationParser.php       # Blueprint → GoatSchema
│        ErdParser.php                # ERD → GoatSchema (infer types)
├── Schema/GoatSchema|Table|Column|Relationship  # ← single source
├── Generators/* (9)                  # Model/Migration/Request/Resource/Controller/Service/Repository/Policy/Test
├── Support/NameResolver|StubRenderer|FileWriter|GoatConfig
└── GoatServiceProvider.php           # config + publish + command
```

No giant generator. Each artifact isolated, stub-driven, testable.

---

### 🧪 Tests

```bash
composer install
composer test # vendor/bin/phpunit — 40 tests
```

Covers: migration/ERD parsing, columns, relationships, naming, 9 generators, CLI, `--only`/`--except`/`--force`, file protection, stubs.

---

### 📦 Install (GitHub)

```bash
composer config repositories.goat vcs https://github.com/CodeWithTeds/laravel-goat.git
composer require goat/laravel-goat:@dev --dev
```
Once on Packagist: `composer require goat/laravel-goat --dev`

Requires `PHP ^8.3` · `Laravel 11|12|13`

---

### 🗺️ Roadmap

- `--api` / `--web` presets, enum casts, factories, `--all` for multi-table ERD, `goat:make --from=openapi`

PRs welcome. Build your next feature with `php artisan goat:make`.

---

<div align="center">

**Built for builders who ship features, not boilerplate.**

MIT · [Report issue](https://github.com/CodeWithTeds/laravel-goat/issues) · `php artisan goat:make`

</div>
