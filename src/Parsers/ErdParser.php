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
 * Parses a textual ERD format into GoatSchema.
 *
 * Supported format:
 *
 *   products
 *   ---------
 *   id bigint PK
 *   category_id bigint FK -> categories.id
 *   name varchar
 *   price decimal(10,2)
 *   stock integer default 0
 *   created_at timestamp
 *   updated_at timestamp
 *
 * Also supports:
 *   - columns: "name varchar(255) nullable unique default 'foo'"
 *   - PK / PRIMARY KEY
 *   - FK -> other_table.column
 *   - FK, FOREIGN KEY
 *   - unique, nullable, default
 *   - unsigned
 *   - PK + FK together
 *
 * Multiple tables may be defined sequentially.
 */
final class ErdParser
{
    private const TYPE_MAP = [
        'bigint' => 'bigInteger',
        'biginteger' => 'bigInteger',
        'int' => 'integer',
        'integer' => 'integer',
        'smallint' => 'smallInteger',
        'tinyint' => 'tinyInteger',
        'varchar' => 'string',
        'char' => 'char',
        'text' => 'text',
        'mediumtext' => 'text',
        'longtext' => 'text',
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'timestamp',
        'timestamptz' => 'timestamp',
        'json' => 'json',
        'jsonb' => 'json',
        'uuid' => 'uuid',
        'ulid' => 'ulid',
        'enum' => 'enum',
        'string' => 'string',
    ];

    /**
     * @throws \InvalidArgumentException
     */
    public function parse(string $input, ?string $fallbackTable = null): GoatSchema
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException('ERD input is empty.');
        }

        $schema = new GoatSchema();

        $lines = preg_split('/\R/', $input) ?: [];
        $currentTable = null;
        $foundAnyTable = false;

        $i = 0;
        $total = count($lines);

        while ($i < $total) {
            $rawLine = $lines[$i];
            $line = trim($rawLine);

            // Skip empty
            if ($line === '') {
                $i++;
                continue;
            }

            // Detect table header:
            // - line is just a name (e.g., "products") followed by dashed line
            // - or line is "Table: products" ?
            // Our primary detection: next non-empty line is all dashes/equals
            $nextIdx = $i + 1;
            while ($nextIdx < $total && trim($lines[$nextIdx]) === '') {
                $nextIdx++;
            }

            $isHeader = false;
            $headerName = null;

            if ($nextIdx < $total) {
                $nextLine = trim($lines[$nextIdx]);
                if (preg_match('/^[-=_]{3,}$/', $nextLine)) {
                    // Current line is table name
                    $headerCandidate = $line;
                    // Validate it looks like a table name (no spaces, except maybe?)
                    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $headerCandidate)) {
                        $isHeader = true;
                        $headerName = $headerCandidate;
                    }
                }
            }

            // Also support "tableName\n--------" OR fallback: line without spaces and no column-like tokens
            // If we haven't found any table yet and line is single word snake, treat as table header even without dashes?
            // But we prefer explicit dashed separator. We'll also allow detection if line is single word and following lines look like columns.

            if ($isHeader) {
                // Finish previous table
                if ($currentTable !== null) {
                    $schema->addTable($currentTable);
                }
                $tableName = Str::snake($headerName);
                $currentTable = new GoatTable(
                    name: $tableName,
                    modelName: NameResolver::modelName($tableName),
                );
                $foundAnyTable = true;
                $i = $nextIdx + 1; // skip dashed line
                continue;
            }

            // If no table yet, but input has no dashed headers, try to infer single table
            if ($currentTable === null) {
                if (! $foundAnyTable) {
                    // Heuristic: if line looks like a column definition (contains type word), start implicit table
                    if ($this->looksLikeColumn($line)) {
                        $tableName = $fallbackTable ? Str::snake(Str::plural(Str::snake($fallbackTable))) : 'items';
                        // If fallback is model name, derive table
                        if ($fallbackTable !== null && preg_match('/^[A-Z]/', $fallbackTable)) {
                            $tableName = NameResolver::tableName($fallbackTable);
                        } elseif ($fallbackTable !== null) {
                            $tableName = Str::snake($fallbackTable);
                        }
                        $currentTable = new GoatTable(
                            name: $tableName,
                            modelName: NameResolver::modelName($tableName),
                        );
                        $foundAnyTable = true;
                        // Don't increment i yet, parse this line as column
                    } else {
                        // Treat line as table header without dashes (single word)
                        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $line) && ! $this->looksLikeColumn($line)) {
                            $tableName = Str::snake($line);
                            $currentTable = new GoatTable(name: $tableName, modelName: NameResolver::modelName($tableName));
                            $foundAnyTable = true;
                            $i++;
                            continue;
                        }
                        $i++;
                        continue;
                    }
                } else {
                    $i++;
                    continue;
                }
            }

            // At this point we have a currentTable and line should be a column
            // Try to parse ANY non-empty line as a column (supports single-token columns like "product_name" without type)
            if ($currentTable !== null) {
                // Skip dashed lines that might appear as separators inside table (already handled as header, but just in case)
                if (preg_match('/^[-=_]{3,}$/', $line)) {
                    $i++;
                    continue;
                }
                $col = $this->parseColumnLine($line, $currentTable);
                if ($col !== null) {
                    $currentTable->addColumn($col);
                    if ($col->isForeignKey && $col->foreignTable !== null) {
                        $relatedModel = NameResolver::modelName($col->foreignTable);
                        $methodName = NameResolver::relationMethodForForeignKey($col->name);
                        $currentTable->addRelationship(new GoatRelationship(
                            type: GoatRelationship::BELONGS_TO,
                            relatedTable: $col->foreignTable,
                            relatedModel: $relatedModel,
                            foreignKey: $col->name,
                            ownerKey: $col->foreignColumn ?? 'id',
                            methodName: $methodName,
                        ));
                    }
                    if (in_array($col->name, ['created_at', 'updated_at'], true)) {
                        $currentTable->hasTimestamps = true;
                    }
                    if ($col->name === 'deleted_at') {
                        $currentTable->hasSoftDeletes = true;
                    }
                }
                // If parse returned null but line looks like a column (e.g., malformed), skip silently
                // Do NOT treat single-word column names as new tables — rely solely on dashed-line headers for new tables
            }

            $i++;
        }

        if ($currentTable !== null) {
            $schema->addTable($currentTable);
        }

        if ($schema->count() === 0) {
            throw new \InvalidArgumentException(
                'Unable to parse ERD. Expected format: table name on one line, dashed separator, then columns like "name varchar".'
            );
        }

        // If only one table and fallback suggests different name, we already used fallback for implicit.
        // Post-process: if schema has one table named "items" but fallback provided, rename?
        if ($schema->count() === 1 && $fallbackTable !== null) {
            $primary = $schema->primaryTable();
            if ($primary && $primary->name === 'items') {
                $desiredTable = Str::snake(Str::plural(Str::snake($fallbackTable)));
                if (preg_match('/^[A-Z]/', $fallbackTable)) {
                    $desiredTable = NameResolver::tableName($fallbackTable);
                }
                // Only rename if desired differs; recreate
                if ($desiredTable !== $primary->name) {
                    // Use reflection to rename? Simpler: create new table copying columns
                    $newTable = new GoatTable(name: $desiredTable, modelName: NameResolver::modelName($desiredTable));
                    foreach ($primary->columns as $c) {
                        $newTable->addColumn($c);
                    }
                    foreach ($primary->relationships as $r) {
                        $newTable->addRelationship($r);
                    }
                    $newTable->hasTimestamps = $primary->hasTimestamps;
                    $newTable->hasSoftDeletes = $primary->hasSoftDeletes;
                    $newSchema = new GoatSchema();
                    $newSchema->addTable($newTable);
                    return $newSchema;
                }
            }
        }

        return $schema;
    }

    private function looksLikeColumn(string $line): bool
    {
        // Must contain at least 2 tokens, first is column name, second is type-ish
        // Exclude lines that are obviously headers (single word)
        if (! str_contains($line, ' ') && ! str_contains($line, "\t")) {
            return false;
        }
        $parts = preg_split('/\s+/', $line, 3) ?: [];
        if (count($parts) < 2) {
            return false;
        }
        $typeCandidate = strtolower($parts[1]);
        // Strip size like varchar(255) or decimal(10,2)
        $typeCandidate = preg_replace('/\(.*\)/', '', $typeCandidate) ?? $typeCandidate;

        $known = array_keys(self::TYPE_MAP);
        // Also allow direct types like string, etc. We consider anything as column if first token is snake
        if (in_array($typeCandidate, $known, true)) {
            return true;
        }
        // Allow FK/ PK lines like "id bigint PK" where second is type; fallback: if second token looks like type-ish word
        if (preg_match('/^[a-z]+(\(.*\))?$/', $typeCandidate)) {
            // Check if it contains FK/PK markers in line -> definitely column
            if (preg_match('/\b(PK|FK|PRIMARY|FOREIGN|UNIQUE|NULLABLE|DEFAULT|REFERENCES)\b/i', $line)) {
                return true;
            }
            // If type candidate is unknown but line has many tokens, treat as column anyway if first token is snake
            if (preg_match('/^[a-z_][a-z0-9_]*$/i', $parts[0])) {
                return true;
            }
        }
        return false;
    }

    private function parseColumnLine(string $line, GoatTable $table): ?GoatColumn
    {
        // Example: "category_id bigint FK -> categories.id nullable default 0 unique"
        // Normalize arrow spacing
        $original = $line;

        // Extract FK relation first: -> table.column
        $foreignTable = null;
        $foreignColumn = 'id';
        $isForeign = false;

        if (preg_match('/->\s*([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z_][a-zA-Z0-9_]*)/', $line, $fm)) {
            $foreignTable = Str::snake($fm[1]);
            $foreignColumn = $fm[2];
            $isForeign = true;
            // Remove the arrow part for further parsing
            $line = preg_replace('/->\s*[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*/', '', $line) ?? $line;
        } elseif (preg_match('/\bFK\b/i', $line)) {
            $isForeign = true;
            // FK without explicit target -> infer from column name
            // Keep isForeign true, foreignTable will be inferred later
        }

        // Tokenize but keep quoted defaults correctly
        // We split respecting quotes
        $tokens = $this->tokenize($line);

        if (count($tokens) === 0) {
            return null;
        }

        // Support single-token columns (e.g., "product_name") -> default type inferred from name
        if (count($tokens) === 1) {
            $colName = Str::snake($tokens[0]);
            // Infer type for well-known names
            $typeRaw = $this->inferTypeForColumn($colName);
            $tokens = [$tokens[0], $typeRaw]; // normalize for later logic
        } else {
            $colName = Str::snake($tokens[0]);
            $typeRaw = strtolower($tokens[1]);
            // If second token is actually a modifier (PK, UNIQUE, FK, etc.) not a type, infer type from column name
            $modifiers = ['pk', 'fk', 'primary', 'unique', 'nullable', 'default', 'unsigned', 'index', 'references'];
            $maybeModifier = strtolower(preg_replace('/\(.*\)/', '', $typeRaw) ?? $typeRaw);
            if (!array_key_exists($maybeModifier, self::TYPE_MAP) && in_array($maybeModifier, $modifiers, true)) {
                // No explicit type given, e.g., "sku UNIQUE" or "id PK" where PK is modifier
                $inferred = $this->inferTypeForColumn($colName);
                // Shift tokens: treat type as inferred, and keep original second token as part of modifiers
                $typeRaw = $inferred;
                // Rebuild tokens so that modifier stays in remaining
                // Keep original tokens[1] as part of remaining by not consuming it as type
                // We achieve this by inserting inferred type and keeping original modifier in remaining
                // Simplify: keep colName, set typeRaw to inferred, and prepend original modifier back to remaining handling
                // To do this, we will handle remaining differently: if we inferred, treat original tokens[1] as first modifier
                // Store flag
                $hasInferredType = true;
            } else {
                $hasInferredType = false;
            }
        }

        // Handle type with size: varchar(255) or decimal(10,2) may be tokenized as "varchar(255)" or split
        $length = null;
        $precision = null;
        $scale = null;
        $enumValues = null;

        // Extract size from typeRaw
        $baseType = $typeRaw;
        $sizeStr = null;
        if (preg_match('/^([a-z]+)\((.+)\)$/', $typeRaw, $tm)) {
            $baseType = $tm[1];
            $sizeStr = $tm[2];
        }

        $canonical = self::TYPE_MAP[$baseType] ?? 'string';

        if ($canonical === 'string' || $canonical === 'char') {
            if ($sizeStr !== null && is_numeric(trim($sizeStr))) {
                $length = (int) trim($sizeStr);
            }
        } elseif (in_array($canonical, ['decimal', 'float', 'double'], true)) {
            if ($sizeStr !== null) {
                $parts = array_map('trim', explode(',', $sizeStr));
                if (isset($parts[0]) && is_numeric($parts[0])) {
                    $precision = (int) $parts[0];
                }
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    $scale = (int) $parts[1];
                }
            }
        } elseif ($canonical === 'enum') {
            if ($sizeStr !== null) {
                // enum('draft','published') or enum(draft,published)
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $sizeStr, $em);
                if (! empty($em[1])) {
                    $enumValues = $em[1];
                } else {
                    $enumValues = array_map('trim', explode(',', $sizeStr));
                }
            }
        }

        // Special handling for id
        $isPrimary = false;
        $isUnique = false;
        $isNullable = false;
        $isUnsigned = false;
        $hasDefault = false;
        $defaultVal = null;

        $upperLine = strtoupper($original);
        if (str_contains($upperLine, ' PK') || str_contains($upperLine, 'PRIMARY') || $colName === 'id' && count($tokens) === 2 && $canonical === 'bigInteger') {
            // id bigint PK is common
            if (preg_match('/\bPK\b/i', $original) || $colName === 'id') {
                // Only auto-primary if PK marker or name is id with integer type without other qualifiers
                // We'll check below more precisely
            }
        }

        // Scan tokens after type for modifiers
        // tokens[2..] may contain: PK, FK, UNIQUE, etc. For inferred type case, include original second token as modifier
        if (isset($hasInferredType) && $hasInferredType) {
            $remaining = array_slice($tokens, 1);
            // Remove the inferred type which we inserted? Actually tokens[1] is original modifier, so remaining should be from 1 onwards
            // But we inserted inferred type as tokens[1] earlier? Let's reconstruct correctly
            // For inferred case, tokens was originally [col, modifier, ...], we set typeRaw to inferred, but tokens still [col, modifier]
            // So remaining should be all tokens from 1 onward (including modifier)
            $remaining = array_slice($tokens, 1);
            // However tokens[1] is the modifier (e.g., UNIQUE), which we want to treat as remaining, and typeRaw is inferred
            // Our earlier step set tokens = [col, inferred] but we lost original modifier? We need to fix earlier logic to preserve it
        } else {
            $remaining = array_slice($tokens, 2);
        }
        $remainingStr = implode(' ', $remaining);
        // For inferred case where original modifier was e.g., UNIQUE, ensure remainingStr contains it
        // If hasInferredType and remainingStr is empty but original line had modifier, we can fallback to original modifiers
        if (isset($hasInferredType) && $hasInferredType && $remainingStr === '') {
            // Original tokens without inferred insertion: use original tokens beyond first
            $origTokens = $this->tokenize($original);
            if (count($origTokens) >= 2) {
                $remainingStr = implode(' ', array_slice($origTokens, 1));
                // Remove arrow FK part already handled
                $remainingStr = preg_replace('/->\s*[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*/', '', $remainingStr) ?? $remainingStr;
            }
        }
        $remainingUpper = strtoupper($remainingStr);

        if (preg_match('/\bPK\b/i', $remainingStr) || preg_match('/\bPRIMARY\s+KEY\b/i', $remainingStr)) {
            $isPrimary = true;
        }
        if (preg_match('/\bUNIQUE\b/i', $remainingStr)) {
            $isUnique = true;
        }
        if (preg_match('/\bNULLABLE\b/i', $remainingStr)) {
            $isNullable = true;
        }
        if (preg_match('/\bNOT\s+NULL\b/i', $remainingStr)) {
            $isNullable = false;
        }
        if (preg_match('/\bUNSIGNED\b/i', $remainingStr)) {
            $isUnsigned = true;
        }

        // Default detection: default 0, default 'foo', default "foo", default null, default true, etc.
        if (preg_match('/\bDEFAULT\s+(.+)$/i', $remainingStr, $dm)) {
            $hasDefault = true;
            $rawDef = trim($dm[1]);
            // If rawDef contains multiple tokens after, take first but handle quoted
            // Our tokenization may have split quoted defaults; reassemble from original
            if (preg_match('/\bDEFAULT\s+(.*)/i', $original, $odm)) {
                $rawDefFull = trim($odm[1]);
                // Remove trailing FK/unique/etc that might come after default? But ERD usually default is last.
                // Simplify: if rawDefFull contains spaces, extract first quoted or first word
                if (preg_match('/^([\'"].*?[\'"]|\S+)/', $rawDefFull, $qmm)) {
                    $rawDef = $qmm[1];
                }
            }
            $defaultVal = $this->parseDefaultRaw($rawDef);
        }

        // Infer primary for id column if no explicit PK but column is 'id'
        if ($colName === 'id' && ! $isPrimary) {
            // Consider id as primary unless explicitly marked otherwise
            $isPrimary = true;
            $isUnsigned = true;
        }

        // If FK inferred but no foreignTable yet, infer from column name
        if ($isForeign && $foreignTable === null) {
            $foreignTable = NameResolver::relatedTableFromForeignKey($colName);
            // Try to extract from original line's arrow alternative missed? Already handled.
        }

        // Timestamps special? created_at / updated_at handled in parser caller, but type remains

        $col = new GoatColumn(
            name: $colName,
            type: $isForeign ? 'foreignId' : $canonical,
            nullable: $isNullable,
            default: $defaultVal,
            hasDefault: $hasDefault,
            primary: $isPrimary,
            unique: $isUnique,
            unsigned: $isUnsigned || $isPrimary,
            length: $length,
            precision: $precision,
            scale: $scale,
            enumValues: $enumValues,
            isForeignKey: $isForeign,
            foreignTable: $foreignTable,
            foreignColumn: $foreignColumn,
        );

        return $col;
    }

    private function inferTypeForColumn(string $name): string
    {
        $lower = strtolower($name);
        if ($lower === 'id') {
            return 'bigint';
        }
        if (in_array($lower, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return 'timestamp';
        }
        if (in_array($lower, ['quantity', 'stock', 'reorder_level', 'level', 'count'], true) || str_contains($lower, 'quantity') || str_contains($lower, 'stock') || str_contains($lower, 'reorder')) {
            return 'integer';
        }
        if (in_array($lower, ['price', 'amount', 'cost', 'total'], true) || str_contains($lower, 'price')) {
            return 'decimal';
        }
        if (str_contains($lower, 'description') || str_contains($lower, 'notes') || $lower === 'description') {
            return 'text';
        }
        if (str_contains($lower, 'email')) {
            return 'string';
        }
        return 'string';
    }

    private function tokenize(string $line): array
    {
        // Split preserving quoted strings
        $tokens = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            $prev = $i > 0 ? $line[$i - 1] : '';

            if ($c === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
                $current .= $c;
                continue;
            }
            if ($c === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
                $current .= $c;
                continue;
            }

            if (! $inSingle && ! $inDouble && ctype_space($c)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }
            $current .= $c;
        }
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private function parseDefaultRaw(string $raw): mixed
    {
        $raw = trim($raw);
        // Remove trailing commas or modifiers after
        $raw = rtrim($raw, ',;');

        if (strcasecmp($raw, 'null') === 0) {
            return null;
        }
        if (strcasecmp($raw, 'true') === 0) {
            return true;
        }
        if (strcasecmp($raw, 'false') === 0) {
            return false;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }
        if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) || (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
            return substr($raw, 1, -1);
        }
        return $raw;
    }
}
