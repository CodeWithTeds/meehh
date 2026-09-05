<?php

declare(strict_types=1);

namespace Goat\Commands;

use Goat\Generators\ControllerGenerator;
use Goat\Generators\MigrationGenerator;
use Goat\Generators\ModelGenerator;
use Goat\Generators\PolicyGenerator;
use Goat\Generators\RepositoryGenerator;
use Goat\Generators\RequestGenerator;
use Goat\Generators\ResourceGenerator;
use Goat\Generators\ServiceGenerator;
use Goat\Generators\TestGenerator;
use Goat\Parsers\ErdParser;
use Goat\Parsers\MigrationParser;
use Goat\Schema\GoatSchema;
use Goat\Schema\GoatTable;
use Goat\Support\FileWriter;
use Goat\Support\GoatBanner;
use Goat\Support\GoatConfig;
use Goat\Support\NameResolver;
use Goat\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;

class MakeCommand extends Command
{
    protected $signature = 'goat:make
                            {name : The model name (e.g., Product)}
                            {--from= : Source type: migration, erd, or path to existing migration file}
                            {--only= : Comma-separated list of components to generate (model,migration,request,resource,controller,service,repository,policy,test)}
                            {--except= : Comma-separated list of components to exclude}
                            {--paths= : Comma-separated custom paths, e.g. repository=app/Repositories/Admin,model=app/Domain/Models}
                            {--module= : Module prefix, e.g. Admin → app/Modules/Admin/...}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate Laravel CRUD artifacts from a migration or ERD';

    private const VALID_COMPONENTS = [
        'model',
        'migration',
        'request',
        'resource',
        'controller',
        'service',
        'repository',
        'policy',
        'test',
    ];

    public function handle(
        MigrationParser $migrationParser,
        ErdParser $erdParser,
        StubRenderer $stubs,
        FileWriter $writer,
    ): int {
        $name = (string) $this->argument('name');
        $from = $this->option('from');
        $only = $this->option('only');
        $except = $this->option('except');
        $force = (bool) $this->option('force');

        // Validate name first so banner can show canonical model name
        try {
            $modelName = NameResolver::ensureValidPhpClassName($name);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        // Clear previous runtime path overrides
        GoatConfig::clear();

        // Handle --paths / --module early (so they affect path resolution and banner hints)
        $pathsOption = $this->option('paths');
        $moduleOption = $this->option('module');
        if (($pathsOption !== null && trim((string) $pathsOption) !== '') || ($moduleOption !== null && trim((string) $moduleOption) !== '')) {
            $err = $this->applyCustomPaths($pathsOption ? (string) $pathsOption : null, $moduleOption ? (string) $moduleOption : null);
            if ($err !== true) {
                $this->components->error($err);
                return self::FAILURE;
            }
        }

        // Modern banner: image (meeeh.png) on the left, intro card on the right
        $this->displayHeader($modelName);

        // Validate --only / --except
        $onlyList = $this->parseListOption($only);
        $exceptList = $this->parseListOption($except);

        if ($onlyList !== null) {
            $invalid = array_diff($onlyList, self::VALID_COMPONENTS);
            if (! empty($invalid)) {
                $this->components->error('Invalid --only values: ' . implode(', ', $invalid) . '. Valid: ' . implode(', ', self::VALID_COMPONENTS));
                return self::FAILURE;
            }
        }
        if ($exceptList !== null) {
            $invalid = array_diff($exceptList, self::VALID_COMPONENTS);
            if (! empty($invalid)) {
                $this->components->error('Invalid --except values: ' . implode(', ', $invalid));
                return self::FAILURE;
            }
        }
        if ($onlyList !== null && $exceptList !== null) {
            $this->components->warn('Both --only and --except provided. --only takes precedence, --except will be ignored for those not in --only.');
        }

        // Determine source strategy
        $strategy = null;
        $existingFilePath = null;

        if ($from !== null) {
            $from = trim((string) $from);
            if ($from === 'migration' || $from === 'migrations') {
                $strategy = 'migration';
            } elseif ($from === 'erd') {
                $strategy = 'erd';
            } elseif ($from !== '') {
                // Treat as file path
                $strategy = 'file';
                $existingFilePath = $from;
            }
        }

        if ($strategy === null) {
            $strategy = $this->promptStrategy($modelName);
            if ($strategy === null) {
                return self::FAILURE;
            }
            if ($strategy === 'file') {
                $existingFilePath = $this->promptExistingMigration($modelName);
                if ($existingFilePath === null) {
                    $this->components->error('No migration file selected.');
                    return self::FAILURE;
                }
            }
        }

        // Obtain raw input
        $rawInput = null;
        $parseMode = 'migration'; // migration | erd

        try {
            if ($strategy === 'migration') {
                $rawInput = $this->readMultilineInput("Paste your migration.\nPress Ctrl+D when finished:");
                $parseMode = 'migration';
            } elseif ($strategy === 'erd') {
                $rawInput = $this->readMultilineInput("Paste your ERD.\nPress Ctrl+D when finished:");
                $parseMode = 'erd';
            } elseif ($strategy === 'file') {
                $rawInput = $this->readExistingFile($existingFilePath);
                $parseMode = 'migration';
                if ($rawInput === null) {
                    return self::FAILURE;
                }
            }
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        if ($rawInput === null || trim($rawInput) === '') {
            $this->components->error('No input received. Aborting.');
            $this->line('Hint: Paste your schema and press Ctrl+D (EOF) to finish, or use --from=path/to/file.php');
            return self::FAILURE;
        }

        // Parse
        $schema = null;
        try {
            if ($parseMode === 'migration') {
                $schema = $migrationParser->parse($rawInput, $modelName);
            } else {
                $schema = $erdParser->parse($rawInput, $modelName);
            }
        } catch (\InvalidArgumentException $e) {
            $this->components->error('Parsing failed: ' . $e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->components->error('Unexpected parsing error: ' . $e->getMessage());
            if ($this->getLaravel() !== null && method_exists($this->getLaravel(), 'environment') && $this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }

        if ($schema === null || $schema->count() === 0) {
            $this->components->error('No tables detected in input.');
            return self::FAILURE;
        }

        // Resolve target table
        $targetTable = $schema->primaryTableFor($modelName);
        if ($targetTable === null) {
            $this->components->error("Could not determine table for [{$modelName}]. Found tables: " . implode(', ', array_map(fn ($t) => $t->name, $schema->tables())));
            return self::FAILURE;
        }

        // If schema has multiple tables, inform user but generate only for target (unless they want all)
        if ($schema->count() > 1) {
            $this->line('');
            $this->components->info('Multiple tables detected: ' . implode(', ', array_map(fn ($t) => $t->name . " ({$t->modelName})", $schema->tables())));
            $this->line("Generating for <options=bold>{$targetTable->name}</> ({$targetTable->modelName})");
            $this->line('Tip: Run <fg=cyan>php artisan goat:make {Model}</> separately for other tables.');
        }

        $this->displayDetectedSchema($targetTable);

        $componentsToGenerate = $this->resolveComponents($onlyList, $exceptList);

        $this->line('');
        $this->line('<fg=gray>Generate:</>');
        foreach (self::VALID_COMPONENTS as $comp) {
            $enabled = in_array($comp, $componentsToGenerate, true);
            $icon = $enabled ? '<fg=green>✓</>' : '<fg=gray>○</>';
            $label = Str::studly($comp);
            if ($comp === 'request') {
                $label = 'Requests (Store & Update)';
            }
            $status = $enabled ? '' : ' <fg=gray>(skipped)</>';
            $this->line(" {$icon} {$label}{$status}");
        }

        // Offer interactive path customization if no --paths/--module given and running interactively
        if ($this->input->isInteractive() && empty($pathsOption) && empty($moduleOption)) {
            $this->promptCustomPaths($componentsToGenerate);
        }

        $this->line('');
        $this->components->info('Generating...');

        // Instantiate generators
        $filesystem = new Filesystem();
        $stubRenderer = $stubs ?? new StubRenderer($filesystem);
        $fileWriter = $writer ?? new FileWriter($filesystem);

        $generators = [
            'model' => new ModelGenerator($stubRenderer),
            'migration' => new MigrationGenerator($stubRenderer),
            'request' => new RequestGenerator($stubRenderer),
            'resource' => new ResourceGenerator($stubRenderer),
            'controller' => new ControllerGenerator($stubRenderer),
            'service' => new ServiceGenerator($stubRenderer),
            'repository' => new RepositoryGenerator($stubRenderer),
            'policy' => new PolicyGenerator($stubRenderer),
            'test' => new TestGenerator($stubRenderer),
        ];

        $results = [];
        $skipped = [];
        $errors = [];

        foreach ($componentsToGenerate as $component) {
            $generator = $generators[$component] ?? null;
            if ($generator === null) {
                continue;
            }

            try {
                if ($component === 'request') {
                    /** @var RequestGenerator $generator */
                    $outputs = $generator->generate($targetTable);
                    foreach ($outputs as $out) {
                        $res = $fileWriter->write($out['path'], $out['contents'], $force);
                        $this->handleWriteResult($res, $component, $results, $skipped, $errors);
                    }
                } else {
                    $out = $generator->generate($targetTable);
                    $res = $fileWriter->write($out['path'], $out['contents'], $force);
                    $this->handleWriteResult($res, $component, $results, $skipped, $errors);
                }
            } catch (\Throwable $e) {
                $errors[] = $component . ': ' . $e->getMessage();
                $this->components->error("Failed to generate {$component}: {$e->getMessage()}");
            }
        }

        // Display results
        $this->line('');
        foreach ($results as $r) {
            $rel = $fileWriter->relativePath($r['path']);
            $this->line(" <fg=green>✓</> {$rel}");
        }
        foreach ($skipped as $s) {
            $rel = $fileWriter->relativePath($s['path']);
            $this->line(" <fg=yellow>⚠</> {$rel} <fg=gray>already exists (use --force to overwrite)</>");
        }
        foreach ($errors as $e) {
            $this->line(" <fg=red>✗</> {$e}");
        }

        if (! empty($errors)) {
            $this->line('');
            $this->components->warn('Completed with errors.');
            return self::FAILURE;
        }

        if (! empty($skipped) && empty($results)) {
            $this->line('');
            $this->components->warn('All files already exist. Use --force to overwrite.');
            // Not a failure, but inform
        }

        $this->line('');
        $this->components->info("🐐 GOAT generated {$targetTable->modelName} successfully!");

        if (! empty($results)) {
            $this->line('');
            $this->line('<fg=gray>Files created:</>');
            foreach ($results as $r) {
                $this->line('  • ' . $fileWriter->relativePath($r['path']));
            }
        }

        return self::SUCCESS;
    }

    private function displayHeader(?string $modelName = null): void
    {
        try {
            GoatBanner::render($this->output, $modelName);
        } catch (\Throwable) {
            // Fallback to simple header if banner fails (e.g., missing GD, bad terminal)
            $this->line('');
            $this->line('<fg=yellow>🐐 GOAT</> <fg=gray>— Laravel Feature Generator • github.com/CodeWithTeds/meehh</>');
            $this->line('');
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function parseListOption(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $value));
        $parts = array_map('strtolower', $parts);
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        return $parts;
    }

    /**
     * @param string[]|null $only
     * @param string[]|null $except
     * @return string[]
     */
    private function resolveComponents(?array $only, ?array $except): array
    {
        // Start from config generate toggles
        $enabled = [];
        foreach (self::VALID_COMPONENTS as $comp) {
            $val = GoatConfig::get("goat.generate.{$comp}", null);
            $configEnabled = $val === null ? true : (bool) $val;
            if ($configEnabled) {
                $enabled[] = $comp;
            }
        }

        if ($only !== null) {
            // Intersect with requested only
            $enabled = array_values(array_intersect($enabled, $only));
            // But if config disabled but only explicitly requested, should we still generate?
            // If user explicitly says --only=model, they expect it even if config disabled.
            // So add missing from only that were not in enabled due to config
            foreach ($only as $o) {
                if (! in_array($o, $enabled, true) && in_array($o, self::VALID_COMPONENTS, true)) {
                    $enabled[] = $o;
                }
            }
            // Preserve order of VALID_COMPONENTS
            $enabled = array_values(array_intersect(self::VALID_COMPONENTS, $enabled));
        }

        if ($except !== null) {
            $enabled = array_values(array_diff($enabled, $except));
        }

        return $enabled;
    }

    private function promptStrategy(string $modelName): ?string
    {
        // Use Laravel Prompts if available (Laravel 10+)
        if (function_exists('Laravel\Prompts\select')) {
            $choice = select(
                label: "How do you want to define {$modelName}?",
                options: [
                    'migration' => 'Paste Migration',
                    'erd' => 'Paste ERD',
                    'file' => 'Use Existing Migration',
                ],
                default: 'migration',
            );

            return $choice;
        }

        // Fallback to legacy choice
        $choice = $this->choice(
            "How do you want to define {$modelName}?",
            ['Paste Migration', 'Paste ERD', 'Use Existing Migration'],
            0
        );

        return match ($choice) {
            'Paste Migration' => 'migration',
            'Paste ERD' => 'erd',
            'Use Existing Migration' => 'file',
            default => 'migration',
        };
    }

    private function promptExistingMigration(string $modelName): ?string
    {
        $files = $this->discoverMigrationFiles($modelName);

        if (empty($files)) {
            $this->components->warn('No migration files found in database/migrations.');
            // Allow manual path entry
            $manual = $this->ask('Enter path to migration file (or leave empty to cancel)');
            if (is_string($manual) && trim($manual) !== '' && file_exists(trim($manual))) {
                return trim($manual);
            }
            return null;
        }

        $options = [];
        foreach ($files as $idx => $file) {
            $base = basename($file);
            $options[$file] = $base;
        }

        if (function_exists('Laravel\Prompts\select')) {
            $choice = select(
                label: 'Select a migration file',
                options: $options,
            );
            return $choice;
        }

        $choice = $this->choice('Select a migration file', array_values($options), 0);
        // Map back to path
        $index = array_search($choice, array_values($options), true);
        if ($index !== false) {
            return array_keys($options)[$index];
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function discoverMigrationFiles(string $modelName): array
    {
        $candidates = [];

        $base = null;
        if (function_exists('database_path')) {
            try { $base = \database_path('migrations'); } catch (\Throwable) { $base = getcwd() . '/database/migrations'; }
        } else {
            $base = getcwd() . '/database/migrations';
        }

        if (! is_dir($base)) {
            return [];
        }

        $table = NameResolver::tableName($modelName);
        $singular = Str::singular($table);

        $all = glob(rtrim($base, '/') . '/*.php') ?: [];

        // Prioritize files that contain table name
        $prioritized = [];
        $others = [];

        foreach ($all as $f) {
            $basename = strtolower(basename($f));
            if (str_contains($basename, $table) || str_contains($basename, $singular)) {
                $prioritized[] = $f;
            } else {
                $others[] = $f;
            }
        }

        // Return prioritized first, then others (limit to 20)
        $merged = array_merge($prioritized, $others);
        return array_slice($merged, 0, 20);
    }

    private function readExistingFile(string $path): ?string
    {
        $path = trim($path);

        // Resolve relative to base_path if needed
        if (! file_exists($path) && function_exists('base_path')) {
            try {
                $candidate = rtrim(\base_path(), '/') . '/' . ltrim($path, '/');
                if (file_exists($candidate)) {
                    $path = $candidate;
                }
            } catch (\Throwable) {}
        }

        if (! file_exists($path)) {
            $this->components->error("File not found: {$path}");
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->components->error("Unable to read file: {$path}");
            return null;
        }

        $this->components->info("Using migration: " . basename($path));

        return $contents;
    }

    private function readMultilineInput(string $prompt): ?string
    {
        $this->line('');
        $this->line("<fg=cyan>{$prompt}</>");
        $this->line('<fg=gray>Tip: Paste your code and press Ctrl+D (EOF) to finish.</>');

        // If input is piped or via --from, we should read STDIN directly
        // For interactive, we read from STDIN until EOF

        // Detect if STDIN has piped data without interaction? We'll just read.
        // Use stream_get_contents(STDIN) which blocks until EOF.

        // Ensure we prompt clearly
        $this->output->write('<fg=gray>> </>');

        // Read all stdin
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open STDIN.');
        }

        // Set to blocking
        stream_set_blocking($handle, true);

        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            return null;
        }

        // Trim but keep structure
        $contents = trim($contents);

        // If empty and user is on interactive terminal without pipe, maybe they didn't paste?
        // We fallback to asking for file? But spec says read multiline until Ctrl+D.

        return $contents;
    }

    private function displayDetectedSchema(GoatTable $table): void
    {
        $this->line('');
        $this->components->info('✓ Schema detected');
        $this->line('');

        $this->line(" <fg=cyan>{$table->modelName}</> <fg=gray>({$table->name})</>");
        $cols = $table->columns;
        $total = count($cols);
        foreach ($cols as $idx => $col) {
            $isLast = $idx === $total - 1;
            $prefix = $isLast ? '└──' : '├──';
            $fk = $col->isForeignKey && $col->foreignTable ? " <fg=gray>→ {$col->foreignTable}.{$col->foreignColumn}</>" : '';
            $extra = [];
            if ($col->primary) {
                $extra[] = 'PK';
            }
            if ($col->unique) {
                $extra[] = 'unique';
            }
            if ($col->nullable) {
                $extra[] = 'nullable';
            }
            $extraStr = $extra ? ' <fg=gray>(' . implode(', ', $extra) . ')</>' : '';
            $typeStr = " <fg=gray>{$col->type}</>";
            $this->line(" {$prefix} {$col->name}{$typeStr}{$fk}{$extraStr}");
        }
    }

    /**
     * @param array{path:string, written:bool, skipped:bool, error:?string} $res
     * @param array<int, array{path:string}> $results
     * @param array<int, array{path:string}> $skipped
     * @param string[] $errors
     */
    private function handleWriteResult(array $res, string $component, array &$results, array &$skipped, array &$errors): void
    {
        if ($res['error'] !== null) {
            $errors[] = $res['error'];
            $this->components->error($res['error']);
            return;
        }
        if ($res['skipped']) {
            $skipped[] = $res;
            return;
        }
        if ($res['written']) {
            $results[] = $res;
            $label = match ($component) {
                'model' => 'model',
                'migration' => 'migration',
                'request' => 'request',
                'resource' => 'resource',
                'controller' => 'controller',
                'service' => 'service',
                'repository' => 'repository',
                'policy' => 'policy',
                'test' => 'test',
                default => $component,
            };
            $this->line(" <fg=green>✓</> {$label} <fg=gray>{$res['path']}</>");
        }
    }

    /**
     * Apply --paths and --module overrides.
     * @return true|string true on success, error message on failure
     */
    private function applyCustomPaths(?string $pathsOption, ?string $moduleOption): true|string
    {
        // Handle --module first (sets defaults for all)
        if ($moduleOption !== null && trim($moduleOption) !== '') {
            $module = trim($moduleOption);
            // Sanitize module name: allow alphanumeric, _, /, \
            if (! preg_match('/^[A-Za-z0-9_\/\\\\]+$/', $module)) {
                return "Invalid --module value [{$module}]. Use alphanumeric, e.g. Admin or Admin/Billing";
            }
            $module = trim($module, '/\\');
            $moduleStudly = collect(explode('/', str_replace('\\', '/', $module)))->map(fn ($p) => Str::studly($p))->implode('/');
            $modulePath = str_replace('/', '/', $moduleStudly); // keep slash
            // Set paths under app/Modules/{Module}/*
            $map = [
                'model' => "app/Modules/{$modulePath}/Models",
                'request' => "app/Modules/{$modulePath}/Http/Requests",
                'resource' => "app/Modules/{$modulePath}/Http/Resources",
                'controller' => "app/Modules/{$modulePath}/Http/Controllers",
                'service' => "app/Modules/{$modulePath}/Services",
                'repository' => "app/Modules/{$modulePath}/Repositories",
                'policy' => "app/Modules/{$modulePath}/Policies",
                'migration' => "database/migrations",
                'test' => "tests/Feature/Modules/{$modulePath}",
            ];
            foreach ($map as $comp => $path) {
                $abs = $this->resolveAbsolutePath($path);
                GoatConfig::set("goat.paths.{$comp}", $abs);
                // Also set namespace to match path (e.g., App\Modules\Admin\Models)
                $ns = $this->pathToNamespace($path);
                GoatConfig::set("goat.namespaces.{$comp}", $ns);
            }
        }

        if ($pathsOption !== null && trim($pathsOption) !== '') {
            $pairs = array_map('trim', explode(',', $pathsOption));
            foreach ($pairs as $pair) {
                if ($pair === '') continue;
                if (! str_contains($pair, '=')) {
                    return "Invalid --paths format [{$pair}]. Use key=path, e.g. --paths=repository=app/Repo/Admin,model=app/Domain/Models";
                }
                [$key, $path] = array_map('trim', explode('=', $pair, 2));
                $key = strtolower($key);
                if (! in_array($key, self::VALID_COMPONENTS, true)) {
                    return "Invalid --paths key [{$key}]. Valid: " . implode(', ', self::VALID_COMPONENTS);
                }
                if ($path === '') {
                    return "Empty path for [{$key}] in --paths";
                }
                $abs = $this->resolveAbsolutePath($path);
                GoatConfig::set("goat.paths.{$key}", $abs);
                // Auto-infer namespace from path (e.g., app/Repo/Admin -> App\Repo\Admin)
                $ns = $this->pathToNamespace($path);
                GoatConfig::set("goat.namespaces.{$key}", $ns);
            }
        }

        return true;
    }

    private function promptCustomPaths(array $components): void
    {
        // Check if interactive and user wants to customize
        try {
            if (function_exists('Laravel\Prompts\confirm')) {
                $want = \Laravel\Prompts\confirm('Customize output paths? (e.g., repo/admin)', default: false);
            } else {
                $want = $this->confirm('Customize output paths?', false);
            }
        } catch (\Throwable) {
            return;
        }

        if (! $want) {
            return;
        }

        $this->line('');
        $this->line('<fg=gray>Leave empty to keep default. Example: app/Repositories/Admin</>');
        $custom = [];
        foreach ($components as $comp) {
            $current = GoatConfig::string("goat.paths.{$comp}", '');
            if ($current === '') {
                // Fallback to generator default (try to infer)
                $current = match ($comp) {
                    'model' => function_exists('app_path') ? (function(){ try{ return \app_path('Models'); } catch(\Throwable){return 'app/Models';}})() : 'app/Models',
                    'repository' => function_exists('app_path') ? (function(){ try{ return \app_path('Repositories'); } catch(\Throwable){return 'app/Repositories';}})() : 'app/Repositories',
                    default => $comp,
                };
                // For display, show relative
                $display = $current;
                if (function_exists('base_path')) {
                    try {
                        $base = \base_path();
                        if (str_starts_with($current, $base)) {
                            $display = ltrim(substr($current, strlen($base)), '/');
                        }
                    } catch (\Throwable) {}
                }
            } else {
                $display = $current;
                // Show relative if possible
                if (function_exists('base_path')) {
                    try {
                        $base = \base_path();
                        if (str_starts_with($display, $base)) {
                            $display = ltrim(substr($display, strlen($base)), '/');
                        }
                    } catch (\Throwable) {}
                }
            }

            $answer = null;
            try {
                if (function_exists('Laravel\Prompts\text')) {
                    $answer = \Laravel\Prompts\text("  {$comp} path", default: $display, hint: "e.g. app/Repositories/Admin");
                } else {
                    $answer = $this->ask("  {$comp} path", $display);
                }
            } catch (\Throwable) {
                continue;
            }

            $answer = trim((string) $answer);
            if ($answer !== '' && $answer !== $display) {
                $custom[$comp] = $answer;
            }
        }

        if (! empty($custom)) {
            $pairs = [];
            foreach ($custom as $k => $v) {
                $pairs[] = "{$k}={$v}";
            }
            $this->applyCustomPaths(implode(',', $pairs), null);
            $this->line('');
            $this->components->info('Custom paths applied:');
            foreach ($custom as $k => $v) {
                $this->line("  <fg=gray>{$k} → {$v}</>");
            }
        }
    }

    private function resolveAbsolutePath(string $path): string
    {
        $path = trim($path);
        // If already absolute (starts with / or C:\), return as is
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return rtrim($path, '/');
        }
        // If starts with app/ or database/ or tests/, resolve via base_path
        if (function_exists('base_path')) {
            try {
                $base = \base_path();
                return rtrim($base, '/') . '/' . ltrim($path, '/');
            } catch (\Throwable) {}
        }
        return rtrim(getcwd() . '/' . ltrim($path, '/'), '/');
    }

    private function pathToNamespace(string $path): string
    {
        // Remove base_path prefix and leading app/
        $p = $path;
        if (function_exists('base_path')) {
            try {
                $base = \base_path();
                if (str_starts_with($p, $base)) {
                    $p = ltrim(substr($p, strlen($base)), '/');
                }
            } catch (\Throwable) {}
        }
        // Also handle getcwd fallback
        $cwd = getcwd();
        if ($cwd && str_starts_with($p, $cwd)) {
            $p = ltrim(substr($p, strlen($cwd)), '/');
        }
        // Now p is like app/Modules/Admin/Models or app/Repositories or repo/admin
        $p = trim($p, '/');
        // Split and studly each segment, but handle well-known prefixes
        $segments = explode('/', str_replace('\\', '/', $p));
        $segments = array_map(fn ($s) => Str::studly($s), $segments);
        // If first segment is App, keep it; otherwise ensure App prefix for app/* paths
        // For paths like app/Repositories/Admin → App\Repositories\Admin
        // For paths like repo/admin → Repo\Admin (keep as is, but studly)
        return implode('\\', $segments);
    }
}
