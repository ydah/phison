<?php

declare(strict_types=1);

namespace Phison\Grammar;

use Phison\Dsl\ActionCode;

final class Production
{
    /**
     * @param list<SymbolRef> $rhs
     */
    public function __construct(
        public readonly int $id,
        public readonly NonTerminal $lhs,
        public readonly array $rhs,
        public readonly ?ActionCode $action,
        public readonly ?Precedence $precedence,
        public readonly ?string $precedenceSymbol = null,
    ) {}

    public function length(): int
    {
        return count($this->rhs);
    }

    public function format(): string
    {
        $rhs = array_map(
            static fn (SymbolRef $ref): string => ($ref->label !== null ? $ref->label . '=' : '') . $ref->symbol->name,
            $this->rhs,
        );

        return $this->lhs->name . ' -> ' . ($rhs === [] ? '/* empty */' : implode(' ', $rhs));
    }
}
