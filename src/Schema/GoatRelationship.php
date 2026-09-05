<?php

declare(strict_types=1);

namespace Goat\Schema;

final class GoatRelationship
{
    public const BELONGS_TO = 'belongsTo';
    public const HAS_MANY = 'hasMany';
    public const HAS_ONE = 'hasOne';
    public const BELONGS_TO_MANY = 'belongsToMany';

    public function __construct(
        public string $type,
        public string $relatedTable,
        public string $relatedModel,
        public string $foreignKey,
        public string $ownerKey = 'id',
        public ?string $methodName = null,
    ) {}

    public function methodName(): string
    {
        if ($this->methodName !== null) {
            return $this->methodName;
        }

        // belongsTo => singular, hasMany => plural
        if ($this->type === self::BELONGS_TO || $this->type === self::HAS_ONE) {
            return lcfirst($this->relatedModel);
        }

        // hasMany / belongsToMany -> plural snake? but method is camel plural
        return lcfirst($this->relatedModel) . 's';
        // Note: caller may override with NameResolver for proper pluralization
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'relatedTable' => $this->relatedTable,
            'relatedModel' => $this->relatedModel,
            'foreignKey' => $this->foreignKey,
            'ownerKey' => $this->ownerKey,
        ];
    }
}
