<?php

declare(strict_types=1);

namespace Goat\Parsers;

use Goat\Schema\GoatColumn;
use Goat\Schema\GoatRelationship;
use Goat\Schema\GoatSchema;
use Goat\Schema\GoatTable;
use Goat\Support\NameResolver;
use Illuminate\Support\Str;

/**
 * Parses Laravel Blueprint migration code into GoatSchema.
 *
 * Supports common Blueprint methods and chained modifiers.
 * Designed to be extensible: add entries to COLUMN_MAP / MODIFIER_PARSERS.
 */
final class MigrationParser
{
    /**
     * Blueprint column type -> canonical GoatColumn type
     */
    private const COLUMN_MAP = [
        'id' => 'bigInteger',
        'bigIncrements' => 'bigInteger',
        'increments' => 'integer',
        'bigInteger' => 'bigInteger',
        'unsignedBigInteger' => 'unsignedBigInteger',
        'integer' => 'integer',
        'smallInteger' => 'smallInteger',
        'tinyInteger' => 'tinyInteger',
        'mediumInteger' => 'integer',
        'foreignId' => 'foreignId',
        'foreignUuid' => 'uuid',
        'foreignUlid' => 'ulid',
        'string' => 'string',
        'char' => 'char',
        'text' => 'text',
        'mediumText' => 'text',
        'longText' => 'text',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
        'dateTime' => 'datetime',
        'timestamp' => 'timestamp',
        'timestamps' => 'timestamps',
        'softDeletes' => 'softDeletes',
        'softDeletesTz' => 'softDeletes',
        'uuid' => 'uuid',
        'ulid' => 'ulid',
        'json' => 'json',
        'jsonb' => 'json',
        'enum' => 'enum',
        'binary' => 'binary',
        'year' => 'integer',
        'time' => 'string',
        'ipAddress' => 'string',
        'macAddress' => 'string',
        'rememberToken' => 'string',
    ];

    /**
     * Parse raw migration / Schema::create snippet.
     *
     * @throws \InvalidArgumentException on empty/invalid input
     */
    public function parse(string $input, ?string $fallbackTable = null): GoatSchema
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException('Migration input is empty. Paste a valid Schema::create(...) block.');
        }

        $schema = new GoatSchema();

        // Try to extract Schema::create / Schema::table blocks
        $tables = $this->extractTables($input);

        if (empty($tables) && $fallbackTable !== null) {
            // Input may be just column lines without Schema wrapper
            $tables[] = [
                'name' => $this->resolveTableName($fallbackTable),
                'body' => $input,
            ];
        }

        if (empty($tables)) {
            throw new \InvalidArgumentException(
                "Unable to detect table name. Ensure your migration contains Schema::create('table', ...) or pass a model name."
            );
        }

        foreach ($tables as $tbl) {
            $tableName = $tbl['name'];
            $body = $tbl['body'];

            $table = new GoatTable(
                name: $tableName,
                modelName: NameResolver::modelName($tableName),
            );

            $lines = $this->splitStatements($body);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '#')) {
                    continue;
                }
                $this->parseStatement($line, $table);
            }

            $schema->addTable($table);
        }

        return $schema;
    }

    /**
     * Extract tables from Schema::create / Schema::table calls.
     *
     * @return array<int, array{name:string, body:string}>
     */
    private function extractTables(string $input): array
    {
        $result = [];

        // Match Schema::create('table', function (Blueprint $table) { ... })
        // We do a simple brace-counting extraction rather than full PHP parsing.
        $pattern = '/Schema\s*::\s*create\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function\s*\([^)]*\)\s*\{/';

        if (preg_match_all($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $fullMatch) {
                $tableName = $matches[1][$idx][0];
                $offset = $matches[0][$idx][1] + strlen($fullMatch[0]) - 1; // position at {
                $body = $this->extractBraceContent($input, $offset);
                if ($body !== null) {
                    $result[] = ['name' => $tableName, 'body' => $body];
                }
            }
        }

        // Also handle Schema::table for completeness (alter) — treat similarly
        $patternTable = '/Schema\s*::\s*table\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function\s*\([^)]*\)\s*\{/';
        if (preg_match_all($patternTable, $input, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $fullMatch) {
                $tableName = $matches[1][$idx][0];
                $offset = $matches[0][$idx][1] + strlen($fullMatch[0]) - 1;
                $body = $this->extractBraceContent($input, $offset);
                if ($body !== null) {
                    // Avoid duplicates if already captured via create
                    $exists = false;
                    foreach ($result as $r) {
                        if ($r['name'] === $tableName) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $result[] = ['name' => $tableName, 'body' => $body];
                    }
                }
            }
        }

        return $result;
    }

    private function extractBraceContent(string $input, int $openPos): ?string
    {
        $depth = 0;
        $len = strlen($input);
        $start = -1;
        for ($i = $openPos; $i < $len; $i++) {
            $char = $input[$i];
            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i + 1;
                }
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0 && $start !== -1) {
                    return substr($input, $start, $i - $start);
                }
                if ($depth < 0) {
                    return null;
                }
            }
        }
        return null;
    }

    /**
     * Split body into statements handling semicolons inside strings/brackets.
     *
     * @return string[]
     */
    private function splitStatements(string $body): array
    {
        $statements = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $bracketDepth = 0;
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $char = $body[$i];
            $prev = $i > 0 ? $body[$i - 1] : '';

            if ($char === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
            } elseif ($char === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
            }

            if (! $inSingle && ! $inDouble) {
                if ($char === '(' || $char === '[') {
                    $bracketDepth++;
                } elseif ($char === ')' || $char === ']') {
                    $bracketDepth = max(0, $bracketDepth - 1);
                }

                if ($char === ';' && $bracketDepth === 0) {
                    $statements[] = $current;
                    $current = '';
                    continue;
                }
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }

    private function parseStatement(string $statement, GoatTable $table): void
    {
        // Normalize: remove $table-> prefix, allow \t and spaces
        if (! str_contains($statement, '->')) {
            return;
        }

        // Handle $table->timestamps(); $table->softDeletes(); $table->id(); etc.
        // Extract chain: method('args')->modifier()->...
        // We parse the whole statement as chain.

        // Remove leading $variable-> if any (support $table)
        $statement = preg_replace('/^\s*\$\w+\s*->\s*/', '', $statement) ?? $statement;

        // Special quick handlers without args
        if (preg_match('/^timestamps\s*\(\s*\)/', $statement)) {
            $table->hasTimestamps = true;
            $table->addColumn(new GoatColumn(name: 'created_at', type: 'timestamp', nullable: true));
            $table->addColumn(new GoatColumn(name: 'updated_at', type: 'timestamp', nullable: true));
            return;
        }

        if (preg_match('/^(nullableTimestamps|timestampsTz)\s*\(/', $statement)) {
            $table->hasTimestamps = true;
            $table->addColumn(new GoatColumn(name: 'created_at', type: 'timestamp', nullable: true));
            $table->addColumn(new GoatColumn(name: 'updated_at', type: 'timestamp', nullable: true));
            return;
        }

        if (preg_match('/^softDeletes\s*\(/', $statement)) {
            $table->hasSoftDeletes = true;
            $table->addColumn(new GoatColumn(name: 'deleted_at', type: 'timestamp', nullable: true, isSoftDelete: true));
            return;
        }

        if (preg_match('/^rememberToken\s*\(/', $statement)) {
            $table->addColumn(new GoatColumn(name: 'remember_token', type: 'string', nullable: true, length: 100));
            return;
        }

        // Handle unique/index on existing column: $table->unique('email');
        if (preg_match('/^(unique|index|primary)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $statement, $m)) {
            $colName = $m[2];
            $existing = $table->getColumn($colName);
            if ($existing) {
                if ($m[1] === 'unique') {
                    $existing->unique = true;
                } elseif ($m[1] === 'primary') {
                    $existing->primary = true;
                } else {
                    $existing->indexed = true;
                }
            }
            return;
        }

        // General column: method('name', ...)->chain
        // Use manual parentheses matching to avoid greedy regex issues (e.g., ->unique() chain)
        $parsed = $this->parseMethodCall($statement);
        if ($parsed === null) {
            // Might be method without parentheses like $table->id
            if (preg_match('/^(\w+)\s*$/', trim($statement), $m2)) {
                $method = $m2[1];
                $this->handleSimpleColumn($method, '', '', $table);
                return;
            }
            return;
        }
        [$method, $argsRaw, $chain] = $parsed;

        // Map method to type
        $canonical = self::COLUMN_MAP[$method] ?? null;

        if ($canonical === null) {
            // Unsupported method -> keep but warn? We treat as string
            $canonical = 'string';
        }

        // Handle shorthand primary keys
        if (in_array($method, ['id', 'bigIncrements', 'increments'], true)) {
            $table->addColumn(new GoatColumn(name: 'id', type: $canonical, primary: true, unsigned: true));
            return;
        }

        if ($method === 'ulid' && trim($argsRaw) === '') {
            // $table->ulid() defaults to id? In Laravel it creates 'ulid' column? Treat as primary-ish string
            $table->addColumn(new GoatColumn(name: 'id', type: 'ulid', primary: true));
            return;
        }

        // Extract column name (first quoted string arg)
        $columnName = $this->extractFirstQuoted($argsRaw);
        $extraArgs = $this->extractExtraArgs($argsRaw, $columnName);

        // timestamps etc already handled
        if ($method === 'timestamps' || $method === 'softDeletes') {
            return;
        }

        $this->handleSimpleColumn($method, $columnName ?? '', $chain, $table, $canonical, $extraArgs, $argsRaw);
    }

    private function handleSimpleColumn(
        string $method,
        string $columnName,
        string $chain,
        GoatTable $table,
        ?string $canonical = null,
        array $extraArgs = [],
        string $argsRaw = '',
    ): void {
        $canonical ??= self::COLUMN_MAP[$method] ?? 'string';

        // If no column name extracted and method expects one, skip
        if ($columnName === '' && ! in_array($method, ['id', 'timestamps', 'softDeletes', 'rememberToken'], true)) {
            return;
        }

        $col = new GoatColumn(name: $columnName, type: $canonical);

        // Length for string/char: $table->string('name', 100)
        if (in_array($method, ['string', 'char'], true) && isset($extraArgs[0]) && is_numeric($extraArgs[0])) {
            $col->length = (int) $extraArgs[0];
        }

        // decimal precision/scale: decimal('price', 10, 2)
        if (in_array($method, ['decimal', 'float', 'double'], true)) {
            if (isset($extraArgs[0]) && is_numeric($extraArgs[0])) {
                $col->precision = (int) $extraArgs[0];
            }
            if (isset($extraArgs[1]) && is_numeric($extraArgs[1])) {
                $col->scale = (int) $extraArgs[1];
            }
        }

        // Enum values
        if ($method === 'enum') {
            $values = $this->extractEnumValues($argsRaw);
            $col->enumValues = $values;
        }

        // Detect modifiers in chain
        $chainLower = strtolower($chain);

        if (str_contains($chainLower, '->nullable(')) {
            $col->nullable = true;
        }
        if (str_contains($chainLower, '->unique(')) {
            $col->unique = true;
        }
        if (str_contains($chainLower, '->primary(')) {
            $col->primary = true;
        }
        if (str_contains($chainLower, '->index(')) {
            $col->indexed = true;
        }
        if (str_contains($chainLower, '->unsigned(')) {
            $col->unsigned = true;
        }

        // default(...)
        if (preg_match('/->default\s*\(\s*(.+?)\s*\)/', $chain, $dm)) {
            $col->default = $this->parseDefaultValue(trim($dm[1]));
            $col->hasDefault = true;
        }

        // constrained() handling for foreignId
        $isForeign = false;
        $foreignTable = null;
        $foreignColumn = 'id';

        if ($method === 'foreignId' || str_contains($chainLower, '->constrained(')) {
            // foreignId('category_id')->constrained()
            // foreignId('user_id')->constrained('users')
            // ->constrained() without args infers table from column
            if ($method === 'foreignId' || str_contains($chainLower, 'constrained')) {
                $isForeign = true;
                // Try to extract constrained args
                if (preg_match('/->constrained\s*\(\s*(?:[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?)?\s*\)/', $chain, $cm)) {
                    $foreignTable = $cm[1] ?? null;
                    $foreignColumn = $cm[2] ?? 'id';
                    if ($foreignTable === '' || $foreignTable === null) {
                        $foreignTable = NameResolver::relatedTableFromForeignKey($columnName);
                    }
                } else {
                    // foreignId without ->constrained still is FK, but table inferred
                    $foreignTable = NameResolver::relatedTableFromForeignKey($columnName);
                }
                // Also handles ->references('id')->on('users')
                if (preg_match('/->references\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*->on\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $chain, $rm)) {
                    $foreignColumn = $rm[1];
                    $foreignTable = $rm[2];
                } elseif (preg_match('/->on\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $chain, $om)) {
                    $foreignTable = $om[1];
                }
            }
        }

        // If column name ends with _id and type is unsignedBigInteger / bigInteger, might be FK even without constrained?
        // We only mark as FK if constrained or foreignId
        if ($isForeign) {
            $col->isForeignKey = true;
            $col->foreignTable = $foreignTable;
            $col->foreignColumn = $foreignColumn;
            // Ensure type is foreignId canonical
            if ($method !== 'foreignId' && $col->type === 'unsignedBigInteger') {
                $col->type = 'foreignId';
            }
        }

        // Primary id already handled above; still add if columnName empty? Already returned.
        if ($col->name === '' || $col->name === null) {
            return;
        }

        $table->addColumn($col);

        if ($isForeign && $foreignTable !== null) {
            $relatedModel = NameResolver::modelName($foreignTable);
            $methodName = NameResolver::relationMethodForForeignKey($columnName);
            $table->addRelationship(new GoatRelationship(
                type: GoatRelationship::BELONGS_TO,
                relatedTable: $foreignTable,
                relatedModel: $relatedModel,
                foreignKey: $columnName,
                ownerKey: $foreignColumn,
                methodName: $methodName,
            ));
        }
    }

    private function extractFirstQuoted(string $argsRaw): ?string
    {
        if (preg_match('/[\'"]([^\'"]+)[\'"]/', $argsRaw, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract arguments after the first quoted string.
     *
     * @return string[]
     */
    private function extractExtraArgs(string $argsRaw, ?string $firstQuoted): array
    {
        if ($firstQuoted === null) {
            return [];
        }
        // Find position after first quoted
        $pos = strpos($argsRaw, $firstQuoted);
        if ($pos === false) {
            return [];
        }
        $after = substr($argsRaw, $pos + strlen($firstQuoted));
        // Find closing quote then split by comma
        $after = preg_replace('/^[\'"]\s*,?\s*/', '', $after) ?? $after;
        $after = trim($after);
        if ($after === '') {
            return [];
        }
        // Split respecting brackets/quotes
        $parts = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $bracketDepth = 0;
        $len = strlen($after);
        for ($i = 0; $i < $len; $i++) {
            $c = $after[$i];
            $prev = $i > 0 ? $after[$i - 1] : '';
            if ($c === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
            } elseif ($c === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
            }
            if (! $inSingle && ! $inDouble) {
                if ($c === '[' || $c === '(') {
                    $bracketDepth++;
                } elseif ($c === ']' || $c === ')') {
                    $bracketDepth = max(0, $bracketDepth - 1);
                }
                if ($c === ',' && $bracketDepth === 0) {
                    $parts[] = trim($current);
                    $current = '';
                    continue;
                }
            }
            $current .= $c;
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }
        // Clean quotes
        $parts = array_map(fn ($p) => trim($p, " \t\n\r\0\x0B'\""), $parts);
        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    private function extractEnumValues(string $argsRaw): ?array
    {
        // enum('status', ['draft','published'])
        if (preg_match('/\[\s*(.*?)\s*\]/s', $argsRaw, $m)) {
            $inner = $m[1];
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $inner, $vals);
            return $vals[1] ?? null;
        }
        return null;
    }

    private function parseDefaultValue(string $raw): mixed
    {
        $raw = trim($raw);
        if ($raw === 'null' || $raw === 'NULL') {
            return null;
        }
        if (strtolower($raw) === 'true') {
            return true;
        }
        if (strtolower($raw) === 'false') {
            return false;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }
        // Strip quotes
        if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) || (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
            return substr($raw, 1, -1);
        }
        return $raw;
    }

    private function resolveTableName(string $fallback): string
    {
        // If fallback is Model name (Studly), convert to snake plural
        if (preg_match('/^[A-Z]/', $fallback)) {
            return Str::plural(Str::snake($fallback));
        }
        return Str::snake($fallback);
    }

    /**
     * Parse a method call like: string('name')->nullable()->unique()
     * Returns [method, argsRaw, chain] or null if not a method call.
     */
    private function parseMethodCall(string $statement): ?array
    {
        $statement = trim($statement);

        // Find method name (leading word)
        if (! preg_match('/^(\w+)\s*\(/', $statement, $m)) {
            return null;
        }

        $method = $m[1];
        $openPos = strpos($statement, '(');
        if ($openPos === false) {
            return null;
        }

        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $len = strlen($statement);
        $closePos = null;

        for ($i = $openPos; $i < $len; $i++) {
            $char = $statement[$i];
            $prev = $i > 0 ? $statement[$i - 1] : '';

            if ($char === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
                continue;
            }
            if ($char === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
                continue;
            }

            if ($inSingle || $inDouble) {
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $closePos = $i;
                    break;
                }
            }
        }

        if ($closePos === null) {
            return null;
        }

        $argsRaw = substr($statement, $openPos + 1, $closePos - $openPos - 1);
        $chain = substr($statement, $closePos + 1);
        // Trim chain; remove trailing semicolon if any (should already be stripped but keep)
        $chain = trim($chain);
        // Remove trailing semicolon for chain detection (splitStatements already removed it)
        $chain = rtrim($chain, '; ');

        return [$method, $argsRaw, $chain];
    }
}
