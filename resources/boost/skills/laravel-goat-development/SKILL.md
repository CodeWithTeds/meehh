---
name: laravel-goat-development
description: Use GOAT for migration- or ERD-driven Laravel feature scaffolding when meehh/laravel-goat is installed or the user explicitly mentions GOAT.
---

# Laravel GOAT Development

## When to use this skill

Use this skill when the application has `meehh/laravel-goat` installed, or when the user explicitly asks about GOAT, and the task involves generating a Laravel feature from a migration or ERD.

GOAT generates a feature scaffold, not a complete production feature. A default run generates the complete feature slice: model, migration, Store and Update form requests, JSON resource, controller, service, repository, policy, and feature test.

## Inspect the application first

Before generating anything, inspect:

- `composer.json` to confirm that `meehh/laravel-goat` is installed
- `config/goat.php`, if present
- nearby features and their namespaces
- published stubs under `resources/stubs/vendor/goat/`, if present
- existing migrations, routes, factories, policies, and tests

Follow the application's established paths, namespaces, naming, and integration conventions. GOAT's Service, Repository, Resource, Controller, Policy, and Test components are part of its core feature-slice architecture; do not silently remove them just because the application does not currently use those layers. If the user explicitly asks for GOAT or a complete feature slice, preserve the full component set unless the user requests a subset. If an architectural mismatch is a concern, explain it and ask before narrowing the generation. Do not introduce a separate module or unrelated application-wide architecture merely because GOAT can generate one.

## Agent workflow and schema gathering

Use this decision flow before invoking GOAT:

1. Discover the schema from the project first. Read an existing migration, ERD, or other authoritative schema source when one is available. If Boost's read-only database schema tool is available and the database is the source of truth, inspect it before asking the user for details. GOAT does not consume database-tool output directly, so convert the result into explicit migration or ERD input.
2. If the schema is missing or ambiguous, ask focused questions about the model/table name, columns and database types, primary key, nullability, defaults, indexes, unique constraints, foreign keys, timestamps, soft deletes, and required generated components. Do not invent business-critical columns or constraints.
3. Summarize the resulting schema and any assumptions. In planning mode, show the proposed input and command but do not run `goat:make`; wait for implementation authorization when the agent's workflow requires it.
4. During execution, provide the confirmed schema to GOAT through an existing file, a temporary input file, or an explicit STDIN heredoc. Do not ask the user to paste schema manually into a terminal.
5. Unless the user requests selected artifacts, plan a full GOAT feature slice. Adapt destinations and namespaces to the application, but do not replace GOAT's components with unrelated Laravel generators or reduce the component list silently.

Agents should not run a bare interactive `php artisan goat:make Product` command as an automated step. That form can block while waiting for the user to choose an input source and send EOF. Prefer explicit `--from` values and non-interactive input.

## Choose a schema source

Prefer an existing migration when one already defines the feature. Pass its path explicitly and exclude migration generation so that the existing migration is not duplicated:

```bash
php artisan goat:make Product \
    --from=database/migrations/2026_01_01_000000_create_products_table.php \
    --except=migration
```

For migration syntax supplied through standard input:

```bash
cat migration.php | php artisan goat:make Product --from=migration
```

For a plain-text ERD supplied through standard input:

```bash
cat erd.txt | php artisan goat:make Product --from=erd
```

Interactive runs prompt for the schema source and read pasted input until EOF. Explicit `--from` values are preferred for repeatable or automated workflows.

## Select generated components

Available components are:

```text
model, migration, request, resource, controller, service, repository, policy, test
```

The `request` component generates both Store and Update form requests.

Use `--only` when adding specific missing artifacts:

```bash
php artisan goat:make Product --from=path/to/migration.php --only=model,resource,request
```

Use `--except` when generating the normal scaffold with a small number of exclusions:

```bash
php artisan goat:make Product --from=path/to/migration.php --except=migration,policy
```

Do not combine `--only` and `--except`; keep the requested component set unambiguous.

When the user asks for the complete GOAT architecture, omit both flags. Use `--only` or `--except` only when the user explicitly requests a subset, an existing artifact should be preserved, or a specific project constraint requires an exclusion that the user has accepted.

## Paths and modules

Inspect nearby features before choosing custom destinations. Per-run paths use comma-separated `component=path` pairs, and namespaces are inferred from the paths:

```bash
php artisan goat:make Product \
    --from=path/to/migration.php \
    --paths=model=app/Domain/Models,repository=app/Domain/Repositories
```

For an application that already uses modules:

```bash
php artisan goat:make Product --from=path/to/migration.php --module=Inventory
```

Use modules only when the application already follows that architecture or the user explicitly requests it.

## Protect existing application code

GOAT does not overwrite existing files unless `--force` is supplied. Treat `--force` as destructive:

- inspect every target file before overwriting it
- use it only when regeneration is intentional and reviewed
- never add it automatically to an existing application workflow

When only some artifacts are missing, prefer `--only` over regenerating the whole feature.

## Review the scaffold before considering the task complete

Schema inference is a starting point. Review the generated code for:

- namespaces, paths, fillable attributes, casts, and relationships
- domain validation that cannot be inferred from the database
- authorization rules and policy behavior
- route registration and route-model binding
- model factories required by generated tests
- dependency injection and application-specific business logic
- formatting and the project's relevant test suite

Generated policies may be permissive placeholders, and generated tests assume application routes and factories exist. Add or adapt those pieces to match the application before treating the feature as complete.
