<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class ProductionDefinition
{
    /**
     * @param list<SymbolReference> $rhs
     */
    public function __construct(
        public readonly array $rhs,
        public readonly ActionCode $action,
        public readonly ?string $precedenceSymbol = null,
    ) {
    }
}
