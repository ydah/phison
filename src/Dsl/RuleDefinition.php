<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class RuleDefinition
{
    /**
     * @param list<ProductionDefinition> $productions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $productions,
    ) {
    }
}
