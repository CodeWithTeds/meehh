<?php

declare(strict_types=1);

namespace Goat\Schema;

/**
 * Represents a single database column / model attribute.
 */
final class GoatColumn
{
    public function __construct(
        public string $name,
        public string $type = 'string',
        public bool $nullable = false,
        public mixed $default = null,
        public bool $hasDefault = false,
        public bool $primary = false,
        public bool $unique = false,
        public bool $indexed = false,
        public bool $unsigned = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public ?array $enumValues = null,
        public bool $isForeignKey = false,
        public ?string $foreignTable = null,
        public ?string $foreignColumn = null,
        public bool $isSoftDelete = false,
        public bool $isTimestamps = false,
    ) {}

    public function isFillable(): bool
    {
        if ($this->primary) {
            return false;
        }
        if (in_array($this->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return false;
        }
        if ($this->isTimestamps || $this->isSoftDelete) {
            return false;
        }
        return true;
    }

    public function castType(): ?string
    {
        return match ($this->type) {
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'unsignedBigInteger' => 'integer',
            'decimal', 'float', 'double' => 'decimal:2',
            'boolean' => 'boolean',
            'json', 'jsonb' => 'array',
            'datetime', 'timestamp', 'date', 'dateTime' => 'datetime',
            'uuid' => 'string',
            default => null,
        };
    }

    public function phpFakerType(): string
    {
        return match ($this->type) {
            'string', 'text', 'char', 'uuid', 'enum' => "'example'",
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'unsignedBigInteger', 'foreignId' => 'fake()->randomNumber()',
            'decimal', 'float', 'double' => 'fake()->randomFloat(2, 0, 999)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime', 'timestamp', 'dateTime' => 'fake()->dateTime()->format(\"Y-m-d H:i:s\")',
            'json', 'jsonb' => '[]',
            default => "'example'",
        };
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'primary' => $this->primary,
            'unique' => $this->unique,
            'unsigned' => $this->unsigned,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'isForeignKey' => $this->isForeignKey,
            'foreignTable' => $this->foreignTable,
        ];
    }
}
